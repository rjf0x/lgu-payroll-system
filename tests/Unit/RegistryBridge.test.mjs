import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { RegistryStore } from '../../app/Services/Blockchain/RegistryStore.mjs';
import { RegistryBridge } from '../../app/Services/Blockchain/RegistryBridge.mjs';
import { createRegistryServer } from '../../app/Services/Blockchain/RegistryHttpServer.mjs';
import { BridgeError, idempotencyKey, registrationRequest } from '../../app/Services/Blockchain/RegistryProtocol.mjs';

const subject = { organization_id: 'lgu-example', payroll_reference: '123e4567-e89b-42d3-a456-426614174000', revision: 1 };
const digest = 'd084d482d01003c66c28e2e7331c1933217001e5b717861dba0eab4ad9535836';
const request = { ...subject, payload_hash: digest, hash_schema_version: 1, idempotency_key: idempotencyKey(subject) };
const token = 'synthetic-test-token-with-at-least-32-bytes';

function fixture(t) {
    const directory = mkdtempSync(join(tmpdir(), 'besu-bridge-test-'));
    const path = join(directory, 'journal.sqlite');
    let store = new RegistryStore(path, { chain: 'synthetic-test' });
    let clock = 100000;
    const calls = { signs: 0, broadcasts: [] };
    const gateway = {
        networkIdentity: () => ({ chain_id: '1337' }),
        registrarAuthorized: async organization => organization === 'lgu-example',
        nodeStatus: async () => ({ block_number: 1, block_timestamp: 1700, syncing: false }),
        health: async () => gateway.ready(),
        ready: async () => {}, registration: async () => null, pendingNonce: async () => 4,
        prepare: async (record, nonce) => ({ nonce, data: record.payload_hash }),
        sign: async envelope => { calls.signs++; return { raw: `signed-${envelope.nonce}`, hash: `hash-${envelope.nonce}` }; },
        broadcast: async (raw, hash) => { calls.broadcasts.push({ raw, hash }); }, receipt: async () => null,
    };
    let bridge = new RegistryBridge(store, gateway, () => clock);
    t.after(() => { store.close(); rmSync(directory, { recursive: true, force: true }); });
    return {
        get store() { return store; }, get bridge() { return bridge; }, gateway, calls, path,
        advance() { clock += 400000; },
        restart() {
            store.close();
            store = new RegistryStore(path, { chain: 'synthetic-test' });
            bridge = new RegistryBridge(store, gateway, () => clock);
        },
    };
}

test('rejects invalid identities, extra payroll fields, bad digests and idempotency keys', () => {
    const invalid = [
        { organization_id: 1 }, { organization_id: '-bad' }, { payroll_reference: subject.payroll_reference.toUpperCase() },
        { revision: '1' }, { revision: 0 }, { revision: 4294967296 }, { payload_hash: '0'.repeat(64) },
        { payload_hash: digest.toUpperCase() }, { hash_schema_version: 2 }, { idempotency_key: 'wrong' }, { employee_name: 'Private' },
    ];
    for (const patch of invalid) {
        assert.throws(() => registrationRequest({ ...request, ...patch }), BridgeError);
    }
    assert.deepEqual(registrationRequest(request), request);
});

test('queues durably, deduplicates requests and rejects conflicting hashes', t => {
    const f = fixture(t);
    assert.equal(f.bridge.enqueue(request).status, 'QUEUED');
    f.restart();
    assert.equal(f.bridge.enqueue(request).status, 'QUEUED');
    assert.equal(f.store.all().length, 1);
    assert.throws(() => f.bridge.enqueue({ ...request, payload_hash: '1'.repeat(64) }), { code: 'REGISTRATION_CONFLICT', status: 409 });
    assert.equal(f.store.get(request.idempotency_key).payload_hash, digest);
});

test('excludes a second writer and rejects reusing a journal for another network', t => {
    const f = fixture(t);
    assert.throws(() => new RegistryStore(f.path, { chain: 'synthetic-test' }));
    f.store.close();
    assert.throws(() => new RegistryStore(f.path, { chain: 'different' }), { code: 'JOURNAL_IDENTITY_MISMATCH' });
    f.restart();
});

test('saves signed bytes and transaction identity before broadcasting', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    f.gateway.broadcast = async (raw, hash) => {
        const saved = f.store.get(request.idempotency_key);
        assert.equal(saved.raw_transaction, raw);
        assert.equal(saved.transaction_hash, hash);
        assert.equal(saved.status, 'RECONCILING');
    };

    await f.bridge.processPending();

    assert.equal(f.store.get(request.idempotency_key).status, 'SUBMITTED');
    const response = f.bridge.enqueue(request);
    assert.equal(Object.hasOwn(response, 'raw_transaction'), false);
    assert.equal(Object.hasOwn(response, 'envelope'), false);
});

test('replays identical signed bytes after a lost response and process restart', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    f.gateway.broadcast = async raw => {
        f.calls.broadcasts.push(raw);
        throw new Error('Sensitive upstream response');
    };
    await f.bridge.processPending();
    assert.equal(f.store.get(request.idempotency_key).status, 'RECONCILING');
    assert.equal(f.store.get(request.idempotency_key).last_error, 'UPSTREAM_UNAVAILABLE');
    f.restart();
    f.advance();
    f.gateway.broadcast = async raw => { f.calls.broadcasts.push(raw); };

    await f.bridge.processPending();

    assert.equal(f.calls.signs, 1);
    assert.deepEqual(f.calls.broadcasts, ['signed-4', 'signed-4']);
    assert.equal(f.store.get(request.idempotency_key).status, 'SUBMITTED');
});

test('never broadcasts if signed transaction persistence fails', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    const save = f.store.save.bind(f.store);
    f.store.save = record => {
        if (record.raw_transaction) {
            throw new Error('Journal disk full');
        }
        return save(record);
    };

    await assert.rejects(f.bridge.processPending(), /Journal disk full/);

    assert.equal(f.calls.broadcasts.length, 0);
    assert.equal(f.store.get(request.idempotency_key).transaction_hash, null);
});

test('reconciles a mined registration without signing or broadcasting again', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    await f.bridge.processPending();
    f.restart();
    f.gateway.registration = async () => ({ payload_hash: digest, hash_schema_version: 1, transaction_hash: 'original' });
    f.gateway.receipt = async hash => ({ successful: true, transaction_hash: hash });

    await f.bridge.processPending();

    assert.equal(f.store.get(request.idempotency_key).status, 'CONFIRMED');
    assert.equal(f.calls.signs, 1);
    assert.equal(f.calls.broadcasts.length, 1);
});

test('records an on-chain conflict without broadcasting or replacing the request hash', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    f.gateway.registration = async () => ({ payload_hash: '1'.repeat(64), hash_schema_version: 1 });

    await f.bridge.processPending();

    assert.equal(f.store.get(request.idempotency_key).status, 'CONFLICT');
    assert.equal(f.store.get(request.idempotency_key).payload_hash, digest);
    assert.equal(f.calls.signs, 0);
});

test('blocks later nonces when another registrar wins while a signed transaction is unresolved', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    await f.bridge.processPending();
    f.bridge.enqueue({ ...request, revision: 2, idempotency_key: idempotencyKey({ ...subject, revision: 2 }) });
    f.gateway.registration = async record => record.revision === 1 ? { payload_hash: '1'.repeat(64), hash_schema_version: 1 } : null;

    await f.bridge.processPending();

    assert.equal(f.store.get(request.idempotency_key).last_error, 'CONFLICT_WITH_UNRESOLVED_NONCE');
    assert.equal(f.store.all()[1].nonce, null);
    assert.equal(f.calls.broadcasts.length, 1);
});

test('releases an unsigned reservation when a matching registration already exists', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    f.gateway.sign = async () => { throw new Error('Signer unavailable before broadcast'); };
    await f.bridge.processPending();
    f.advance();
    f.gateway.registration = async () => ({ payload_hash: digest, hash_schema_version: 1 });

    await f.bridge.processPending();

    assert.equal(f.store.get(request.idempotency_key).status, 'CONFIRMED');
    assert.equal(f.store.get(request.idempotency_key).nonce, null);
    assert.equal(f.calls.broadcasts.length, 0);
});

test('retains reverted transaction evidence and never automatically resubmits it', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    await f.bridge.processPending();
    f.gateway.receipt = async hash => ({ successful: false, transaction_hash: hash, block_number: 9, block_hash: 'block',
        receipt: { gasUsed: 10n } });

    await f.bridge.processPending();
    await f.bridge.processPending();

    assert.equal(f.store.get(request.idempotency_key).status, 'FAILED');
    assert.equal(f.store.get(request.idempotency_key).evidence.block_number, 9);
    assert.equal(f.calls.broadcasts.length, 1);
});

test('serializes simultaneous workers and allocates distinct persisted nonces', async t => {
    const f = fixture(t);
    const second = { ...request, revision: 2, idempotency_key: idempotencyKey({ ...subject, revision: 2 }) };
    f.bridge.enqueue(request);
    f.bridge.enqueue(second);

    await Promise.all([f.bridge.processPending(), f.bridge.processPending(), f.bridge.processPending()]);

    assert.equal(f.calls.signs, 2);
    assert.deepEqual(f.store.all().map(row => row.nonce), [4, 5]);
});

test('reuses reserved envelope after signer failure and blocks later nonce allocation during backoff', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    f.bridge.enqueue({ ...request, revision: 2, idempotency_key: idempotencyKey({ ...subject, revision: 2 }) });
    const originalSign = f.gateway.sign;
    f.gateway.sign = async () => { throw new Error('Signer down'); };
    await f.bridge.processPending();
    const reserved = f.store.get(request.idempotency_key).envelope;
    assert.equal(f.store.all()[1].nonce, null);
    f.restart();
    f.gateway.sign = originalSign;
    await f.bridge.processPending();
    assert.equal(f.calls.signs, 0);
    f.advance();

    await f.bridge.processPending();

    assert.deepEqual(f.store.get(request.idempotency_key).envelope, reserved);
    assert.equal(f.calls.signs, 2);
});

test('does not sign or broadcast when network readiness fails', async t => {
    const f = fixture(t);
    f.bridge.enqueue(request);
    f.gateway.ready = async () => { throw new BridgeError('NETWORK_OR_CONTRACT_MISMATCH'); };
    await f.bridge.processPending();
    assert.equal(f.calls.signs, 0);
    assert.equal(f.store.get(request.idempotency_key).last_error, 'NETWORK_OR_CONTRACT_MISMATCH');
});

async function httpFixture(t) {
    const f = fixture(t);
    const server = createRegistryServer(f.bridge, token);
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    t.after(() => new Promise(resolve => { server.close(resolve); server.closeAllConnections(); }));
    const url = `http://127.0.0.1:${server.address().port}`;
    const call = (path, options = {}) => fetch(url + path, { ...options, headers: { authorization: `Bearer ${token}`, ...options.headers } });
    return { ...f, call };
}

test('HTTP requires authentication and queues only valid metadata with 202', async t => {
    const f = await httpFixture(t);
    assert.equal((await f.call('/health', { headers: { authorization: 'Bearer wrong' } })).status, 401);
    const accepted = await f.call('/registrations', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify(request) });
    assert.equal(accepted.status, 202);
    assert.equal((await accepted.json()).status, 'QUEUED');
    const conflict = await f.call('/registrations', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ ...request, payload_hash: '1'.repeat(64) }) });
    assert.equal(conflict.status, 409);
    assert.equal((await f.call('/health')).status, 200);
});

test('HTTP rejects malformed JSON, private fields, large requests and unsupported content types', async t => {
    const f = await httpFixture(t);
    for (const [body, contentType, status] of [['{', 'application/json', 400], ['{}', 'text/plain', 415],
        [JSON.stringify({ ...request, employee_name: 'Private' }), 'application/json', 422], ['a'.repeat(4097), 'application/json', 413]]) {
        const response = await f.call('/registrations', { method: 'POST', headers: { 'content-type': contentType }, body });
        assert.equal(response.status, status);
    }
    assert.equal(f.store.all().length, 0);
});

test('HTTP returns authoritative registration evidence and redacts upstream errors', async t => {
    const f = await httpFixture(t);
    f.gateway.registration = async () => ({ payload_hash: digest, hash_schema_version: 1 });
    const response = await f.call(`/registrations/${subject.organization_id}/${subject.payroll_reference}/1`);
    assert.equal(response.status, 200);
    assert.equal((await response.json()).registration.payload_hash, digest);
    f.gateway.ready = async () => { throw new Error('secret signer credential'); };
    const unavailable = await f.call('/health');
    assert.equal(unavailable.status, 503);
    assert.deepEqual(await unavailable.json(), { error: 'UPSTREAM_UNAVAILABLE' });
});

test('HTTP distinguishes unknown transactions, rejects invalid routes and weak credentials', async t => {
    const f = await httpFixture(t);
    assert.throws(() => createRegistryServer(f.bridge, 'short'), { code: 'TOKEN_MUST_HAVE_AT_LEAST_32_BYTES' });
    const pending = await f.call(`/transactions/0x${'a'.repeat(64)}`);
    assert.equal(pending.status, 200);
    assert.deepEqual(await pending.json(), { status: 'PENDING_OR_UNKNOWN' });
    assert.equal((await f.call('/transactions/invalid')).status, 404);
    assert.equal((await f.call('/health?unexpected=1')).status, 422);
});

test('HTTP registrar checks return only permission and network metadata', async t => {
    const f = await httpFixture(t);
    const allowed = await f.call('/registrars/lgu-example');
    assert.equal(allowed.status, 200);
    assert.deepEqual(await allowed.json(), { network: { chain_id: '1337' }, authorized: true });
    const denied = await f.call('/registrars/other-lgu');
    assert.equal((await denied.json()).authorized, false);
    assert.equal(f.store.all().length, 0);
});
