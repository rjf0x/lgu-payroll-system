import { DatabaseSync } from 'node:sqlite';
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { BridgeError } from './RegistryProtocol.mjs';

/// A dedicated journal is required per signing account; its lifetime SQLite lock excludes other writers.
export class RegistryStore {
    constructor(path, networkIdentity) {
        mkdirSync(dirname(path), { recursive: true, mode: 0o700 });
        this.lock = new DatabaseSync(`${path}.lock`);
        try {
            this.lock.exec('BEGIN EXCLUSIVE');
            this.database = new DatabaseSync(path);
            this.database.exec(`PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL;
                CREATE TABLE IF NOT EXISTS metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL);
                CREATE TABLE IF NOT EXISTS registrations (key TEXT PRIMARY KEY, record TEXT NOT NULL);`);
            const expected = JSON.stringify(networkIdentity);
            const saved = this.database.prepare('SELECT value FROM metadata WHERE key = ?').get('identity');
            if (saved && saved.value !== expected) {
                throw new BridgeError('JOURNAL_IDENTITY_MISMATCH');
            }
            this.database.prepare('INSERT OR IGNORE INTO metadata VALUES (?, ?)').run('identity', expected);
        } catch (error) {
            this.close();
            throw error;
        }
    }

    get(key) {
        const row = this.database.prepare('SELECT record FROM registrations WHERE key = ?').get(key);
        return row ? JSON.parse(row.record) : null;
    }

    all() {
        return this.database.prepare('SELECT record FROM registrations ORDER BY rowid').all().map(row => JSON.parse(row.record));
    }

    enqueue(request) {
        const existing = this.get(request.idempotency_key);
        if (existing) {
            if (existing.payload_hash !== request.payload_hash || existing.hash_schema_version !== request.hash_schema_version) {
                throw new BridgeError('REGISTRATION_CONFLICT', 409);
            }
            return existing;
        }
        return this.save({ ...request, status: 'QUEUED', transaction_hash: null, nonce: null, attempts: 0, retry_count: 0, next_attempt_at: null, last_error: null });
    }

    save(record) {
        this.database.prepare('INSERT INTO registrations VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET record = excluded.record')
            .run(record.idempotency_key, JSON.stringify(record));
        return record;
    }

    close() {
        this.database?.close();
        this.database = null;
        this.lock?.close();
        this.lock = null;
    }
}
