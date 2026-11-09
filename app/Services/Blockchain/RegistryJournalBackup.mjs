import { DatabaseSync, backup } from 'node:sqlite';
import { openSync, closeSync, unlinkSync } from 'node:fs';
import { resolve } from 'node:path';

// The SQLite backup API includes committed WAL data and permits an online source.
export async function backupRegistryJournal(sourcePath, destinationPath) {
    const source = resolve(sourcePath);
    const destination = resolve(destinationPath);
    if (source === destination) {
        throw new Error('Backup destination must differ from the source journal.');
    }
    const input = new DatabaseSync(source, { readOnly: true });
    let created = false;
    try {
        const identity = input.prepare('SELECT value FROM metadata WHERE key = ?').get('identity')?.value;
        if (!identity || input.prepare('PRAGMA quick_check').get().quick_check !== 'ok') {
            throw new Error('Source is not a healthy registry journal.');
        }
        JSON.parse(identity);
        const descriptor = openSync(destination, 'wx', 0o600);
        created = true;
        closeSync(descriptor);
        await backup(input, destination);
        const output = new DatabaseSync(destination, { readOnly: true });
        try {
            if (output.prepare('PRAGMA quick_check').get().quick_check !== 'ok'
                || output.prepare('SELECT value FROM metadata WHERE key = ?').get('identity')?.value !== identity) {
                throw new Error('Backup validation failed.');
            }
            return { destination, registrations: output.prepare('SELECT COUNT(*) AS total FROM registrations').get().total };
        } finally {
            output.close();
        }
    } catch (error) {
        if (created) {
            unlinkSync(destination);
        }
        throw error;
    } finally {
        input.close();
    }
}
