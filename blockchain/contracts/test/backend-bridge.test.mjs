import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { Interface, Transaction, Wallet } from 'ethers';
import { readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
const bridge = fileURLToPath(new URL('../scripts/backend-bridge.mjs', import.meta.url));
const abiPath = fileURLToPath(new URL('../abi/PayrollIntegrityRegistry.json', import.meta.url));
const iface = new Interface(JSON.parse(readFileSync(abiPath)));
function invoke(data) {
  const result = spawnSync(process.execPath, [bridge], { input: JSON.stringify({ abiPath, ...data }), encoding: 'utf8', timeout: 15000 });
  return { status: result.status, value: JSON.parse(result.stdout) };
}
test('ABI encoding and lookup decoding are compatible with deployed registry', () => {
  const reference = `0x${'1'.repeat(64)}`;
  assert.equal(invoke({ operation: 'encode', method: 'lookup', args: [reference] }).value.data, iface.encodeFunctionData('lookup', [reference]));
  const value = iface.encodeFunctionResult('lookup', [[`0x${'2'.repeat(64)}`, '0x0000000000000000000000000000000000000001', 42n, true]]);
  assert.deepEqual(invoke({ operation: 'decode', method: 'lookup', value }).value, { sha256: '2'.repeat(64), writer: '0x0000000000000000000000000000000000000001', anchoredAt: '42', exists: true });
});
test('unsupported operations and malformed inputs fail without disclosing input', () => {
  for (const data of [{ operation: 'broadcast', secret: 'never disclose' }, { operation: 'encode', method: 'record', args: [] }, { operation: 'sign', chainId: 1 }]) {
    assert.deepEqual(invoke(data), { status: 1, value: { error: 'bridge_unavailable' } });
  }
});
test('encrypted synthetic signer produces the expected offline transaction', async () => {
  const directory = mkdtempSync(join(tmpdir(), 'besu-04-'));
  try {
    const wallet = Wallet.createRandom();
    const password = 'ephemeral-test-password';
    const keystorePath = join(directory, 'key.json');
    const passwordPath = join(directory, 'password');
    writeFileSync(keystorePath, await wallet.encrypt(password), { mode: 0o600 });
    writeFileSync(passwordPath, password, { mode: 0o600 });
    const contract = '0x0000000000000000000000000000000000000001';
    const reference = `0x${'1'.repeat(64)}`;
    const result = invoke({ operation: 'sign', chainId: 20261030, nonce: 3, contract, writer: wallet.address, reference, sha256: '2'.repeat(64), keystorePath, passwordPath });
    assert.equal(result.status, 0);
    const tx = Transaction.from(result.value.raw);
    assert.equal(tx.from, wallet.address);
    assert.equal(tx.chainId, 20261030n);
    assert.equal(tx.to, contract);
    assert.equal(tx.nonce, 3);
    assert.equal(tx.value, 0n);
    assert.equal(tx.gasPrice, 0n);
    assert.equal(tx.data, iface.encodeFunctionData('record', [reference, `0x${'2'.repeat(64)}`]));
    assert.equal(invoke({ operation: 'sign', chainId: 20261030, nonce: 3, contract, writer: contract, reference, sha256: '2'.repeat(64), keystorePath, passwordPath }).status, 1);
  } finally { rmSync(directory, { recursive: true, force: true }); }
});
