import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHash, randomUUID } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { BesuRegistryGateway } from '../../app/Services/Blockchain/BesuRegistryGateway.mjs';
import { RegistryStore } from '../../app/Services/Blockchain/RegistryStore.mjs';
import { RegistryBridge } from '../../app/Services/Blockchain/RegistryBridge.mjs';
import { backupRegistryJournal } from '../../app/Services/Blockchain/RegistryJournalBackup.mjs';
import { idempotencyKey } from '../../app/Services/Blockchain/RegistryProtocol.mjs';

test('local Besu recovers an accepted transaction from a journal backup without signing another transaction', {
    skip: process.env.BESU_REGISTRY_PILOT !== 'true', timeout: 60000,
}, async t => {
    const config = JSON.parse(readFileSync('storage/app/private/besu/registry-deployment.json', 'utf8'));
    assert.equal(config.rpcUrl, 'http://127.0.0.1:28545');
    assert.equal(config.chainId, '1337');
    const directory = mkdtempSync(join(tmpdir(), 'registry-live-recovery-'));
    const source = join(directory, 'source.sqlite');
    const snapshot = join(directory, 'snapshot.sqlite');
    const restored = join(directory, 'restored.sqlite');
    let gateway = new BesuRegistryGateway(config);
    let store = new RegistryStore(source, gateway.networkIdentity());
    t.after(() => { store.close(); gateway.close(); rmSync(directory, { recursive: true, force: true }); });
    const nonceBefore = await gateway.pendingNonce();
    const subject = { organization_id: 'lgu-pilot', payroll_reference: randomUUID(), revision: 1 };
    const input = { ...subject, payload_hash: createHash('sha256').update(`Synthetic recovery commitment ${subject.payroll_reference}`).digest('hex'),
        hash_schema_version: 1, idempotency_key: idempotencyKey(subject) };
    let signatures = 0;
    const originalSign = gateway.sign.bind(gateway);
    gateway.sign = async envelope => { signatures++; return originalSign(envelope); };
    const originalBroadcast = gateway.broadcast.bind(gateway);
    gateway.broadcast = async (raw, hash) => {
        await originalBroadcast(raw, hash);
        throw new Error('Synthetic response loss after actual acceptance');
    };
    const bridge = new RegistryBridge(store, gateway);
    bridge.enqueue(input);
    await bridge.processPending();
    const saved = store.get(input.idempotency_key);
    assert.equal(saved.status, 'RECONCILING');
    assert.equal(signatures, 1);
    assert.ok(saved.raw_transaction);
    await backupRegistryJournal(source, snapshot);
    store.close();
    gateway.close();
    await backupRegistryJournal(snapshot, restored);
    gateway = new BesuRegistryGateway(config);
    store = new RegistryStore(restored, gateway.networkIdentity());
    const recoveredSign = gateway.sign.bind(gateway);
    gateway.sign = async envelope => { signatures++; return recoveredSign(envelope); };
    const recovery = new RegistryBridge(store, gateway, () => Date.now() + 600000);
    for (let attempt = 0; attempt < 40; attempt++) {
        await recovery.processPending();
        if (store.get(input.idempotency_key).status === 'CONFIRMED') {
            break;
        }
        await delay(500);
    }
    const result = store.get(input.idempotency_key);
    assert.equal(result.status, 'CONFIRMED', result.last_error ?? 'Unconfirmed');
    assert.equal(result.transaction_hash, saved.transaction_hash);
    assert.equal(result.raw_transaction, saved.raw_transaction);
    assert.equal(signatures, 1);
    assert.equal(await gateway.pendingNonce(), nonceBefore + 1);
    writeFileSync('storage/app/private/besu/pilot-recovery-report.json', JSON.stringify({
        transaction_hash: result.transaction_hash, restored_status: result.status, signatures,
        nonce_before: nonceBefore, nonce_after: nonceBefore + 1, signed_bytes_preserved: true,
    }, null, 2)+'\n', { mode: 0o600 });
});
