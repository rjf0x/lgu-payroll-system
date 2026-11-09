import { backupRegistryJournal } from '../../app/Services/Blockchain/RegistryJournalBackup.mjs';

if (process.argv.length !== 4) {
    throw new Error('Usage: node config/besu/backup-journal.mjs <source-journal> <new-backup-path>');
}
const result = await backupRegistryJournal(process.argv[2], process.argv[3]);
console.log(JSON.stringify(result));
