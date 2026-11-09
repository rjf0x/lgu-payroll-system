import { test } from 'node:test';
import assert from 'node:assert/strict';
import { AbiCoder, Interface, Wallet, keccak256 } from 'ethers';
import { BesuRegistryGateway, registryAbi } from '../../app/Services/Blockchain/BesuRegistryGateway.mjs';

const subject = { organization_id: 'lgu-example', payroll_reference: '123e4567-e89b-42d3-a456-426614174000', revision: 1,
    payload_hash: 'd084d482d01003c66c28e2e7331c1933217001e5b717861dba0eab4ad9535836', hash_schema_version: 1 };
// Public synthetic test key, used only for offline signature assertions.
const wallet = new Wallet(`0x${'11'.repeat(32)}`);
const address = `0x${'22'.repeat(20)}`;
const hash = `0x${'aa'.repeat(32)}`;
const blockHash = `0x${'bb'.repeat(32)}`;
const genesisHash = `0x${'cc'.repeat(32)}`;

function fixture() {
    const gateway = Object.create(BesuRegistryGateway.prototype);
    gateway.config = { chainId: '1337', genesisHash, codeHash: keccak256('0x6000'), gasPrice: '0', deploymentBlock: 1 };
    gateway.address = address;
    gateway.signerAddress = wallet.address;
    gateway.interface = new Interface(registryAbi);
    const key = keccak256(AbiCoder.defaultAbiCoder().encode(['string', 'string', 'uint32'],
        [subject.organization_id, subject.payroll_reference, subject.revision]));
    const encoded = gateway.interface.encodeEventLog(gateway.interface.getEvent('PayrollRegistered'),
        [key, subject.organization_id, subject.payroll_reference, 1, `0x${subject.payload_hash}`, 1, 1700, wallet.address]);
    const log = { ...encoded, address, index: 0, blockHash, transactionHash: hash, removed: false };
    const receipt = { hash, to: address, blockHash, blockNumber: 9, status: 1, logs: [log] };
    const transaction = { hash, to: address, blockHash, chainId: 1337n, from: wallet.address,
        data: gateway.interface.encodeFunctionData('registerPayroll', [subject.organization_id, subject.payroll_reference,
            1, `0x${subject.payload_hash}`, 1]) };
    const stored = { payloadHash: `0x${subject.payload_hash}`, schemaVersion: 1n, registeredAt: 1700n, registrar: wallet.address };
    gateway.rpc = {
        send: async method => { assert.equal(method, 'eth_chainId'); return '0x539'; },
        getBlock: async number => number === 0 ? { hash: genesisHash } : { hash: blockHash, timestamp: 1700 },
        getCode: async () => '0x6000', getTransactionCount: async () => 7,
        estimateGas: async () => 100000n, getLogs: async () => [log],
        getTransactionReceipt: async () => receipt, getTransaction: async () => transaction,
    };
    gateway.contract = { isRegistrar: async () => true, getPayroll: async () => stored };
    gateway.signer = { send: async () => [wallet.address] };
    return { gateway, receipt, transaction, stored, log };
}

test('node status reports syncing and latest block independently of signing', async () => {
    const { gateway } = fixture();
    gateway.rpc.getBlock = async () => ({ number: 9, timestamp: 1700 });
    gateway.rpc.send = async method => { assert.equal(method, 'eth_syncing'); return false; };
    assert.deepEqual(await gateway.nodeStatus(), { block_number: 9, block_timestamp: 1700, syncing: false });
    gateway.rpc.send = async () => ({ currentBlock: '0x9' });
    assert.equal((await gateway.nodeStatus()).syncing, true);
    gateway.rpc.getBlock = async () => null;
    await assert.rejects(gateway.nodeStatus(), { code: 'NODE_UNAVAILABLE' });
});

test('readiness rejects wrong chain, genesis or runtime bytecode', async () => {
    for (const change of [
        gateway => { gateway.rpc.send = async () => '0x1'; },
        gateway => { gateway.config.genesisHash = hash; },
        gateway => { gateway.config.codeHash = hash; },
        gateway => { gateway.rpc.getCode = async () => '0x'; },
    ]) {
        const { gateway } = fixture();
        change(gateway);
        await assert.rejects(gateway.ready(), { code: 'NETWORK_OR_CONTRACT_MISMATCH' });
    }
    await fixture().gateway.ready();
});

test('prepares a legacy zero-value transaction only for an authorized registrar', async () => {
    const { gateway } = fixture();
    gateway.contract.isRegistrar = async () => false;
    await assert.rejects(gateway.prepare(subject, 7), { code: 'SIGNER_NOT_AUTHORIZED' });
    gateway.contract.isRegistrar = async () => true;

    const envelope = await gateway.prepare(subject, 7);

    assert.equal(envelope.gas, '0x1d4c0');
    assert.equal(envelope.nonce, '0x7');
    assert.equal(envelope.chainId, '0x539');
    assert.equal(envelope.value, '0x0');
    const decoded = gateway.interface.parseTransaction({ data: envelope.data });
    assert.equal(decoded.args[3], `0x${subject.payload_hash}`);
});

test('health requires the configured signing account to be available', async () => {
    const { gateway } = fixture();
    assert.deepEqual(await gateway.health(), { chain_id: '1337', genesis_hash: genesisHash,
        contract_address: address, code_hash: keccak256('0x6000'), signer_address: wallet.address.toLowerCase(), deployment_block: 1 });
    gateway.signer.send = async () => [];
    await assert.rejects(gateway.health(), { code: 'SIGNER_UNAVAILABLE' });
});

test('registrar readiness checks the namespace permission without signing', async () => {
    const { gateway } = fixture();
    assert.equal(await gateway.registrarAuthorized('lgu-example'), true);
    gateway.contract.isRegistrar = async () => false;
    assert.equal(await gateway.registrarAuthorized('other-lgu'), false);
    await assert.rejects(gateway.registrarAuthorized('INVALID'), { code: 'INVALID_IDENTITY' });
});

test('validates every signed transaction field before accepting raw bytes', async () => {
    const { gateway } = fixture();
    const envelope = await gateway.prepare(subject, 7);
    const transaction = { to: address, nonce: 7, chainId: 1337, type: 0, value: 0, gasLimit: 120000, gasPrice: 0, data: envelope.data };
    gateway.signer = { send: async (method, args) => {
        assert.equal(method, 'eth_signTransaction');
        assert.deepEqual(args, [envelope]);
        return wallet.signTransaction(transaction);
    } };
    const signed = await gateway.sign(envelope);
    assert.equal(signed.hash, keccak256(signed.raw));
    for (const patch of [{ to: wallet.address }, { nonce: 8 }, { chainId: 1 }, { value: 1 }, { data: '0x' }, { gasLimit: 1 }, { gasPrice: 1 }]) {
        gateway.signer.send = async () => wallet.signTransaction({ ...transaction, ...patch });
        await assert.rejects(gateway.sign(envelope), { code: 'INVALID_SIGNER_RESPONSE' });
    }
    gateway.signer.send = async () => 'not a transaction';
    await assert.rejects(gateway.sign(envelope), { code: 'INVALID_SIGNER_RESPONSE' });
});

test('broadcast recovery accepts already-known evidence only for the exact transaction hash', async () => {
    const { gateway } = fixture();
    gateway.rpc.send = async () => { throw new Error('Ambiguous upstream failure'); };
    assert.equal(await gateway.broadcast('0x01', hash), undefined);
    gateway.rpc.getTransaction = async () => null;
    gateway.rpc.getTransactionReceipt = async () => null;
    await assert.rejects(gateway.broadcast('0x01', hash), /Ambiguous upstream failure/);
    gateway.rpc.getTransaction = async () => ({ hash: genesisHash });
    await assert.rejects(gateway.broadcast('0x01', hash), /Ambiguous upstream failure/);
});

test('reads registered hash from consistent storage, event, receipt, block and transaction evidence', async () => {
    const { gateway } = fixture();

    const evidence = await gateway.registration(subject);

    assert.equal(evidence.payload_hash, subject.payload_hash);
    assert.equal(evidence.transaction_hash, hash);
    assert.equal(evidence.registered_at, '1700');
    assert.equal(evidence.registrar, wallet.address);
    assert.equal(evidence.block_number, 9);
});

test('rejects inconsistent event, receipt, signer, block, calldata and storage evidence', async () => {
    for (const change of [
        f => { f.gateway.rpc.getLogs = async () => []; },
        f => { f.gateway.rpc.getLogs = async () => [f.log, f.log]; },
        f => { f.gateway.rpc.getLogs = async () => [{ ...f.log, removed: true }]; },
        f => { f.receipt.status = 0; },
        f => { f.receipt.logs = []; },
        f => { f.transaction.from = address; },
        f => { f.transaction.blockHash = genesisHash; },
        f => { f.transaction.chainId = 1n; },
        f => { f.transaction.data = f.gateway.interface.encodeFunctionData('registerPayroll', ['other-lgu', subject.payroll_reference, 1, `0x${subject.payload_hash}`, 1]); },
        f => { f.stored.payloadHash = hash; },
        f => { f.gateway.rpc.getBlock = async () => ({ hash: genesisHash, timestamp: 1700 }); },
    ]) {
        const f = fixture();
        change(f);
        await assert.rejects(f.gateway.registration(subject), { code: 'INVALID_EVIDENCE' });
    }
});

test('returns null for absent registrations and unavailable transaction receipts', async () => {
    const { gateway, stored } = fixture();
    stored.registrar = `0x${'00'.repeat(20)}`;
    assert.equal(await gateway.registration(subject), null);
    gateway.rpc.getTransactionReceipt = async () => null;
    assert.equal(await gateway.receipt(hash), null);
});
