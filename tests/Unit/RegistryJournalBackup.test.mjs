import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { RegistryStore } from '../../app/Services/Blockchain/RegistryStore.mjs';
import { backupRegistryJournal } from '../../app/Services/Blockchain/RegistryJournalBackup.mjs';

test('online backups preserve signed transaction recovery state and restore into a fresh journal', async t => {
    const directory = mkdtempSync(join(tmpdir(), 'registry-backup-'));
    const source = join(directory, 'source.sqlite');
    const snapshot = join(directory, 'snapshot.sqlite');
    const restored = join(directory, 'restored.sqlite');
    const identity = { chain: 'synthetic-test' };
    const store = new RegistryStore(source, identity);
    t.after(() => { store.close(); rmSync(directory, { recursive: true, force: true }); });
    const record = { idempotency_key: 'test', nonce: 3, raw_transaction: 'synthetic-signed-bytes', transaction_hash: 'synthetic-hash', status: 'RECONCILING' };
    store.save(record);

    assert.equal((await backupRegistryJournal(source, snapshot)).registrations, 1);
    await backupRegistryJournal(snapshot, restored);
    const recovered = new RegistryStore(restored, identity);
    try {
        assert.deepEqual(recovered.get('test'), record);
    } finally {
        recovered.close();
    }
    const before = readFileSync(snapshot);
    await assert.rejects(backupRegistryJournal(source, snapshot), { code: 'EEXIST' });
    assert.deepEqual(readFileSync(snapshot), before);
    await assert.rejects(backupRegistryJournal(source, source), /must differ/);
});
