import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { createReadStream, createWriteStream, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { pipeline } from 'node:stream/promises';
import { once } from 'node:events';
import { setTimeout as delay } from 'node:timers/promises';

const root = fileURLToPath(new URL('../../', import.meta.url));
const state = resolve(root, 'storage/app/private/besu');
mkdirSync(state, { recursive: true, mode: 0o700 });
const secretFile = resolve(state, 'pilot-mysql.env');
if (!existsSync(secretFile)) {
    writeFileSync(secretFile, `MYSQL_ROOT_PASSWORD=${randomBytes(32).toString('base64url')}\nMYSQL_PASSWORD=${randomBytes(32).toString('base64url')}\n`, { flag: 'wx', mode: 0o600 });
}
function parseEnvironment(text) {
    return Object.fromEntries(text.split(/\r?\n/).filter(line => line && !line.startsWith('#')).map(line => {
        const separator = line.indexOf('=');
        return [line.slice(0, separator), line.slice(separator + 1)];
    }));
}
const mysql = parseEnvironment(readFileSync(secretFile, 'utf8'));
if (!mysql.MYSQL_ROOT_PASSWORD || !mysql.MYSQL_PASSWORD) {
    throw new Error('The private QA credential file is incomplete. Preserve existing database state and repair its credentials before continuing.');
}
function run(command, args, env = {}) {
    return new Promise((resolveRun, reject) => {
        const child = spawn(command, args, { cwd: root, env: { ...process.env, ...env }, stdio: 'inherit', windowsHide: true });
        const timer = setTimeout(() => {
            if (process.platform === 'win32') {
                spawn('taskkill', ['/PID', String(child.pid), '/T', '/F'], { stdio: 'ignore', windowsHide: true });
            } else {
                child.kill('SIGTERM');
            }
            reject(new Error(`${command} exceeded the five-minute pilot step limit.`));
        }, 300000);
        child.once('error', error => {
            clearTimeout(timer);
            reject(error);
        });
        child.once('exit', code => {
            clearTimeout(timer);
            code === 0 ? resolveRun() : reject(new Error(`${command} failed with exit code ${code}`));
        });
    });
}
console.log('Preparing the local Besu network and isolated QA MySQL container.');
await run('powershell', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'config/besu/setup.ps1']);
await run('docker', ['compose', '-f', 'config/besu/pilot.compose.yaml', 'up', '-d', '--wait', '--wait-timeout', '90']);
await run(process.execPath, ['config/besu/setup-registry.mjs']);
await run(process.execPath, ['--test', 'tests/Unit/RegistryLiveRecovery.test.mjs'], { BESU_REGISTRY_PILOT: 'true' });
const metadata = JSON.parse(readFileSync(resolve(state, 'registry-deployment.json'), 'utf8'));
const runDirectory = resolve(state, `pilot-${Date.now()}-${randomBytes(4).toString('hex')}`);
mkdirSync(runDirectory, { recursive: false, mode: 0o700 });
const probe = createServer();
await new Promise(resolveListen => probe.listen(0, '127.0.0.1', resolveListen));
const port = probe.address().port;
await new Promise(resolveClose => probe.close(resolveClose));
const token = randomBytes(32).toString('base64url');
const bridgeEnvironment = {
    BESU_BRIDGE_PORT: String(port), BESU_BRIDGE_TOKEN: token, BESU_BRIDGE_RPC_URL: metadata.rpcUrl,
    BESU_BRIDGE_SIGNER_URL: metadata.signerUrl, BESU_BRIDGE_CHAIN_ID: metadata.chainId, BESU_BRIDGE_GENESIS_HASH: metadata.genesisHash,
    BESU_BRIDGE_CONTRACT_ADDRESS: metadata.contractAddress, BESU_BRIDGE_CODE_HASH: metadata.codeHash,
    BESU_BRIDGE_SIGNER_ADDRESS: metadata.signerAddress, BESU_BRIDGE_DEPLOYMENT_BLOCK: String(metadata.deploymentBlock),
    BESU_BRIDGE_GAS_PRICE: '0', BESU_BRIDGE_JOURNAL_FILE: resolve(runDirectory, 'bridge.sqlite'),
};
const laravelEnvironment = {
    APP_ENV: 'testing', APP_KEY: `base64:${randomBytes(32).toString('base64')}`, APP_CONFIG_CACHE: resolve(runDirectory, 'uncached-config.php'),
    DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: '33316', DB_DATABASE: 'lgu_payroll_qa_registry_pilot',
    DB_USERNAME: 'payroll_pilot', DB_PASSWORD: mysql.MYSQL_PASSWORD, DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'database', BESU_REGISTRY_PILOT: 'true',
    PAYROLL_REGISTRY_ENABLED: 'true', PAYROLL_REGISTRY_ORGANIZATION_ID: 'lgu-pilot', PAYROLL_REGISTRY_BRIDGE_URL: `http://127.0.0.1:${port}`,
    PAYROLL_REGISTRY_BRIDGE_TOKEN: token, PAYROLL_REGISTRY_CHAIN_ID: metadata.chainId, PAYROLL_REGISTRY_GENESIS_HASH: metadata.genesisHash,
    PAYROLL_REGISTRY_CONTRACT_ADDRESS: metadata.contractAddress.toLowerCase(), PAYROLL_REGISTRY_CODE_HASH: metadata.codeHash,
    PAYROLL_REGISTRY_SIGNER_ADDRESS: metadata.signerAddress.toLowerCase(), PAYROLL_REGISTRY_DEPLOYMENT_BLOCK: String(metadata.deploymentBlock),
};
function saveEnvironment(path, environment) {
    writeFileSync(path, Object.entries(environment).map(([key, value]) => `${key}=${value}`).join('\n')+'\n', { flag: 'wx', mode: 0o600 });
}
saveEnvironment(resolve(runDirectory, 'bridge.env'), bridgeEnvironment);
saveEnvironment(resolve(runDirectory, 'laravel.env'), laravelEnvironment);
const bridge = spawn(process.execPath, ['config/besu/bridge.mjs'], {
    cwd: root, env: { ...process.env, ...bridgeEnvironment }, stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true,
});
bridge.stdout.on('data', () => {});
bridge.stderr.on('data', () => {});
try {
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
        if (bridge.exitCode !== null || bridge.signalCode !== null) {
            throw new Error('Pilot bridge exited before readiness. Private journal and configuration were retained.');
        }
        try {
            const response = await fetch(`${laravelEnvironment.PAYROLL_REGISTRY_BRIDGE_URL}/health`, { headers: { authorization: `Bearer ${token}` }, signal: AbortSignal.timeout(2000) });
            ready = response.ok;
        } catch {}
        if (ready) {
            break;
        }
        await delay(500);
    }
    if (!ready) {
        throw new Error('Pilot bridge did not become ready.');
    }
    console.log('Running Laravel computation, approvals, queue submission, and MATCH/MISMATCH checks on QA MySQL.');
    await run('php', ['artisan', 'test', '--compact', 'tests/Feature/PayrollRegistryPilotTest.php'], laravelEnvironment);
    const backupPath = resolve(runDirectory, 'mysql-backup.sql');
    const mysqlContainer = 'lgu-payroll-registry-pilot-mysql-1';
    const dump = spawn('docker', ['exec', mysqlContainer, 'sh', '-c',
        'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump -u "$MYSQL_USER" --single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"'],
    { stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true });
    let dumpErrors = '';
    dump.stderr.on('data', data => { dumpErrors += data; });
    const dumpExit = once(dump, 'exit');
    await pipeline(dump.stdout, createWriteStream(backupPath, { flags: 'wx', mode: 0o600 }));
    if ((await dumpExit)[0] !== 0) {
        throw new Error(`QA database backup failed: ${dumpErrors.slice(0, 500)}`);
    }
    const restoreDatabase = `lgu_payroll_qa_registry_restore_${Date.now()}`;
    if (!/^lgu_payroll_qa_registry_restore_[0-9]+$/.test(restoreDatabase)) {
        throw new Error('Invalid isolated restore target.');
    }
    await run('docker', ['exec', mysqlContainer, 'sh', '-c',
        `MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -e "CREATE DATABASE ${restoreDatabase}; GRANT ALL ON ${restoreDatabase}.* TO 'payroll_pilot'@'%';"`]);
    const restore = spawn('docker', ['exec', '-i', mysqlContainer, 'sh', '-c',
        `MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root ${restoreDatabase}`], { stdio: ['pipe', 'ignore', 'inherit'], windowsHide: true });
    const restoreExit = once(restore, 'exit');
    await pipeline(createReadStream(backupPath), restore.stdin);
    if ((await restoreExit)[0] !== 0) {
        throw new Error('QA database restore failed.');
    }
    const restoredEnvironment = { ...laravelEnvironment, DB_DATABASE: restoreDatabase, BESU_REGISTRY_PILOT: 'false', BESU_REGISTRY_RESTORE_CHECK: 'true' };
    saveEnvironment(resolve(runDirectory, 'restore.env'), restoredEnvironment);
    console.log('Verifying the restored QA database against the original on-chain commitment.');
    await run('php', ['artisan', 'test', '--compact', 'tests/Feature/PayrollRegistryRestoreTest.php'], restoredEnvironment);
    await run('php', ['artisan', 'blockchain:readiness', '--json', '--no-interaction'], restoredEnvironment);
    console.log('Running the existing MySQL concurrency tests on the dedicated source QA database.');
    await run('php', ['artisan', 'test', '--compact', 'tests/Feature/PayrollConcurrencyTest.php'], { ...laravelEnvironment, PAYROLL_REGISTRY_ENABLED: 'false' });
    writeFileSync(resolve(state, 'pilot-latest.json'), JSON.stringify({ completed_at: new Date().toISOString(), run_directory: runDirectory,
        source_database: laravelEnvironment.DB_DATABASE, restore_database: restoreDatabase, contract_address: metadata.contractAddress,
        checks: ['live-journal-recovery', 'laravel-queue-match-mismatch', 'mysql-backup-restore-match', 'readiness', 'mysql-concurrency'],
    }, null, 2)+'\n', { mode: 0o600 });
    console.log(`Pilot passed. Private reports and backups retained in ${runDirectory}. Application .env was not modified.`);
} finally {
    if (bridge.exitCode === null && bridge.signalCode === null) {
        const exit = once(bridge, 'exit');
        bridge.kill();
        await exit;
    }
}
