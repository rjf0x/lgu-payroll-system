import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import { AbiCoder, Interface, Transaction, Wallet, keccak256, toQuantity } from 'ethers';
import { registryAbi } from '../../app/Services/Blockchain/BesuRegistryGateway.mjs';
import { idempotencyKey } from '../../app/Services/Blockchain/RegistryProtocol.mjs';

test('runnable service signs, journals, confirms and reads a registration through ethers HTTP transport', { timeout: 30000 }, async t => {
    const directory = mkdtempSync(join(tmpdir(), 'besu-service-test-'));
    const wallet = new Wallet(`0x${'11'.repeat(32)}`);
    const address = `0x${'22'.repeat(20)}`;
    const genesisHash = `0x${'cc'.repeat(32)}`;
    const blockHash = `0x${'bb'.repeat(32)}`;
    const zeroHash = `0x${'00'.repeat(32)}`;
    const token = 'synthetic-service-test-token-at-least-32-bytes';
    const abi = new Interface(registryAbi);
    const coder = AbiCoder.defaultAbiCoder();
    let signed;
    let receipt;
    let transaction;
    let event;
    let registered;
    let broadcasts = 0;
    let child;
    let output = '';
    const block = (number, hash) => ({ number, hash, parentHash: zeroHash, nonce: '0x0000000000000000',
        sha3Uncles: zeroHash, logsBloom: `0x${'00'.repeat(256)}`, transactionsRoot: zeroHash, stateRoot: zeroHash,
        receiptsRoot: zeroHash, miner: wallet.address, difficulty: '0x0', totalDifficulty: '0x0', extraData: '0x',
        size: '0x0', gasLimit: '0x1c9c380', gasUsed: '0x0', timestamp: '0x6a4', transactions: [], uncles: [], baseFeePerGas: '0x0' });
    const rpc = createServer(async (request, response) => {
        try {
            const chunks = [];
            for await (const chunk of request) {
                chunks.push(chunk);
            }
            const message = JSON.parse(Buffer.concat(chunks).toString());
            let result;
            switch (message.method) {
                case 'eth_chainId': result = '0x539'; break;
                case 'eth_accounts': result = [wallet.address]; break;
                case 'eth_getCode': result = '0x6000'; break;
                case 'eth_blockNumber': result = registered ? '0x1' : '0x0'; break;
                case 'eth_getBlockByNumber': result = message.params[0] === '0x0' ? block('0x0', genesisHash) : block('0x1', blockHash); break;
                case 'eth_getTransactionCount': result = registered ? '0x1' : '0x0'; break;
                case 'eth_estimateGas': result = '0x186a0'; break;
                case 'eth_call': {
                    const call = abi.parseTransaction({ data: message.params[0].data });
                    result = call.name === 'isRegistrar' ? abi.encodeFunctionResult('isRegistrar', [true])
                        : abi.encodeFunctionResult('getPayroll', [registered ?? [zeroHash, 0, 0, `0x${'00'.repeat(20)}`]]);
                    break;
                }
                case 'eth_signTransaction': {
                    const envelope = message.params[0];
                    signed = await wallet.signTransaction({ to: envelope.to, nonce: Number(BigInt(envelope.nonce)),
                        chainId: BigInt(envelope.chainId), gasLimit: BigInt(envelope.gas), gasPrice: BigInt(envelope.gasPrice),
                        value: 0, data: envelope.data, type: 0 });
                    result = signed;
                    break;
                }
                case 'eth_sendRawTransaction': {
                    assert.equal(message.params[0], signed);
                    broadcasts++;
                    const parsed = Transaction.from(signed);
                    const call = abi.parseTransaction({ data: parsed.data }).args;
                    const key = keccak256(coder.encode(['string', 'string', 'uint32'], [call[0], call[1], call[2]]));
                    const encoded = abi.encodeEventLog(abi.getEvent('PayrollRegistered'),
                        [key, call[0], call[1], call[2], call[3], call[4], 1700, wallet.address]);
                    event = { ...encoded, address, blockHash, blockNumber: '0x1', transactionHash: parsed.hash,
                        transactionIndex: '0x0', logIndex: '0x0', removed: false };
                    registered = [call[3], call[4], 1700, wallet.address];
                    transaction = { hash: parsed.hash, nonce: toQuantity(parsed.nonce), blockHash, blockNumber: '0x1',
                        transactionIndex: '0x0', from: wallet.address, to: address, value: '0x0', gas: toQuantity(parsed.gasLimit),
                        gasPrice: '0x0', input: parsed.data, type: '0x0', chainId: '0x539',
                        v: toQuantity(parsed.signature.networkV), r: parsed.signature.r, s: parsed.signature.s };
                    receipt = { transactionHash: parsed.hash, transactionIndex: '0x0', blockHash, blockNumber: '0x1',
                        from: wallet.address, to: address, cumulativeGasUsed: '0x186a0', gasUsed: '0x186a0',
                        contractAddress: null, logs: [event], logsBloom: `0x${'00'.repeat(256)}`,
                        status: '0x1', effectiveGasPrice: '0x0', type: '0x0' };
                    result = parsed.hash;
                    break;
                }
                case 'eth_getTransactionReceipt': result = receipt ?? null; break;
                case 'eth_getTransactionByHash': result = transaction ?? null; break;
                case 'eth_getLogs': result = event ? [event] : []; break;
                default: throw new Error(`Unexpected RPC ${message.method}`);
            }
            response.writeHead(200, { 'content-type': 'application/json' });
            response.end(JSON.stringify({ jsonrpc: '2.0', id: message.id, result }));
        } catch (error) {
            response.writeHead(500, { 'content-type': 'application/json' });
            response.end(JSON.stringify({ error: error.message }));
        }
    });
    t.after(async () => {
        if (child && child.exitCode === null && child.signalCode === null) {
            const exit = once(child, 'exit');
            child.kill();
            await exit;
        }
        await new Promise(resolve => { rpc.close(resolve); rpc.closeAllConnections(); });
        rmSync(directory, { recursive: true, force: true });
    });
    await new Promise(resolve => rpc.listen(0, '127.0.0.1', resolve));
    const rpcUrl = `http://127.0.0.1:${rpc.address().port}`;
    const probe = createServer();
    await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
    const port = probe.address().port;
    await new Promise(resolve => probe.close(resolve));
    child = spawn(process.execPath, [fileURLToPath(new URL('../../config/besu/bridge.mjs', import.meta.url))], {
        windowsHide: true,
        env: { ...process.env, BESU_BRIDGE_PORT: String(port), BESU_BRIDGE_TOKEN: token,
            BESU_BRIDGE_RPC_URL: rpcUrl, BESU_BRIDGE_SIGNER_URL: rpcUrl, BESU_BRIDGE_CHAIN_ID: '1337',
            BESU_BRIDGE_GENESIS_HASH: genesisHash, BESU_BRIDGE_CONTRACT_ADDRESS: address,
            BESU_BRIDGE_CODE_HASH: keccak256('0x6000'), BESU_BRIDGE_SIGNER_ADDRESS: wallet.address,
            BESU_BRIDGE_DEPLOYMENT_BLOCK: '0', BESU_BRIDGE_GAS_PRICE: '0', BESU_BRIDGE_JOURNAL_FILE: join(directory, 'journal.sqlite') },
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    child.stdout.on('data', data => { output += data; });
    child.stderr.on('data', data => { output += data; });
    for (let attempt = 0; !output.includes('listening on loopback') && attempt < 100; attempt++) {
        assert.equal(child.exitCode, null, output);
        await delay(50);
    }
    assert.match(output, /listening on loopback/);
    const url = `http://127.0.0.1:${port}`;
    const headers = { authorization: `Bearer ${token}`, 'content-type': 'application/json' };
    const health = await fetch(`${url}/health`, { headers });
    assert.equal(health.status, 200, await health.text());
    const subject = { organization_id: 'lgu-example', payroll_reference: '123e4567-e89b-42d3-a456-426614174000', revision: 1 };
    const body = { ...subject, payload_hash: 'd084d482d01003c66c28e2e7331c1933217001e5b717861dba0eab4ad9535836',
        hash_schema_version: 1, idempotency_key: idempotencyKey(subject) };
    const accepted = await fetch(`${url}/registrations`, { method: 'POST', headers, body: JSON.stringify(body) });
    assert.equal(accepted.status, 202);
    const path = `${url}/registrations/${subject.organization_id}/${subject.payroll_reference}/1`;
    let result;
    for (let attempt = 0; attempt < 150; attempt++) {
        const response = await fetch(path, { headers });
        assert.equal(response.status, 200, await response.clone().text());
        result = await response.json();
        if (result.submission?.status === 'CONFIRMED') {
            break;
        }
        await delay(100);
    }
    assert.equal(result.submission.status, 'CONFIRMED', JSON.stringify(result));
    assert.equal(result.registration.payload_hash, body.payload_hash);
    assert.equal(result.registration.transaction_hash, transaction.hash);
    assert.equal(Object.hasOwn(result.submission, 'raw_transaction'), false);
    assert.equal(broadcasts, 1);
});
