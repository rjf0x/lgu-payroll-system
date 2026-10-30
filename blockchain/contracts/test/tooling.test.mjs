import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { connect } from '../scripts/runtime.mjs';
import { compile } from '../scripts/compile.mjs';

test('deployment tooling rejects remote, credential-bearing and non-HTTP endpoints before connecting', async () => {
  const previous = process.env.BESU_RPC_URL;
  try {
    for (const endpoint of ['https://example.org', 'http://192.0.2.1:8545', 'http://secret:secret@localhost:18545', 'file:///tmp/rpc']) {
      process.env.BESU_RPC_URL = endpoint;
      await assert.rejects(connect(), /only local HTTP/);
    }
  } finally {
    if (previous === undefined) delete process.env.BESU_RPC_URL;
    else process.env.BESU_RPC_URL = previous;
  }
});
test('published ABI matches the warning-free pinned Shanghai compilation', async () => {
  const artifact = await compile();
  const abi = JSON.parse(await readFile(new URL('../abi/PayrollIntegrityRegistry.json', import.meta.url), 'utf8'));
  assert.deepEqual(abi, artifact.abi);
  assert.match(artifact.compiler, /^0\.8\.38\+/);
  assert.equal(artifact.evmVersion, 'shanghai');
});
