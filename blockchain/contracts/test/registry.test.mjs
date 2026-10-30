import { before, after, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';
import { ContractFactory, ZeroAddress, ZeroHash, sha256, toUtf8Bytes, randomBytes, hexlify } from 'ethers';
import { compile } from '../scripts/compile.mjs';
import { connect, signer, confirmed, deployFixture, txOptions, verifyDeployment } from '../scripts/runtime.mjs';

let provider, genesisHash, artifact, admin, writer, outsider, registry;
const reference = () => hexlify(randomBytes(32));
const digest = sha256(toUtf8Bytes('BESU-02 synthetic snapshot fixture'));
async function succeeds(promise) { return confirmed(provider, await promise); }
async function reverts(call, name) {
  await assert.rejects(call, error => {
    const data = error.data ?? error.info?.error?.data;
    return typeof data === 'string' && registry.interface.parseError(data)?.name === name;
  });
}
before(async () => {
  artifact = await compile();
  ({ provider, genesisHash } = await connect());
  admin = await signer('administrator', provider);
  writer = await signer('writer', provider);
  outsider = await signer('outsider', provider);
});
after(() => provider?.destroy());
beforeEach(async () => { ({ contract: registry } = await deployFixture(artifact, admin, writer)); });

test('deployment is confirmed on real Besu and matches compiled source and authorization', async () => {
  const receipt = await provider.getTransactionReceipt(registry.deploymentTransaction().hash);
  const { keccak256 } = await import('ethers');
  const record = { chainId: 20261030, genesisHash, address: await registry.getAddress(), administrator: admin.address,
    writer: writer.address, transactionHash: receipt.hash, runtimeCodeHash: keccak256(await provider.getCode(await registry.getAddress())) };
  await verifyDeployment(provider, artifact, record);
  assert.equal(await registry.writers(admin.address), false);
  assert.equal(await registry.writers(outsider.address), false);
  await assert.rejects(verifyDeployment(provider, artifact, { ...record, chainId: 1 }), /chain identity/);
  await assert.rejects(verifyDeployment(provider, artifact, { ...record, runtimeCodeHash: ZeroHash }), /Runtime code/);
});
test('unknown references return an explicit absent result for any reader', async () => {
  const value = await registry.connect(outsider).lookup(reference());
  assert.equal(value.exists, false);
  assert.equal(value.sha256, ZeroHash);
});
test('writer stores the exact SHA-256 bytes with immutable metadata and an event', async () => {
  const ref = reference();
  const receipt = await succeeds(registry.connect(writer).record(ref, digest, txOptions));
  const value = await registry.connect(outsider).lookup(ref);
  assert.equal(value.sha256, digest);
  assert.equal(value.writer, writer.address);
  assert.equal(value.exists, true);
  assert.equal(value.anchoredAt, BigInt((await provider.getBlock(receipt.blockNumber)).timestamp));
  const event = registry.interface.parseLog(receipt.logs[0]);
  assert.equal(event.name, 'CommitmentRecorded');
  assert.equal(event.args.batchVersionRef, ref);
  assert.equal(event.args.sha256, digest);
  assert.equal(event.args.writer, writer.address);
});
test('unauthorized writer and administrator-only account cannot create commitments', async () => {
  for (const actor of [outsider, admin]) {
    await reverts(registry.connect(actor).record.staticCall(reference(), digest), 'UnauthorizedWriter');
  }
});
test('unauthorized raw transaction receives a real failed receipt and leaves no record', async () => {
  const ref = reference();
  const sent = await registry.connect(outsider).record(ref, digest, { ...txOptions, gasLimit: 200000n });
  const receipt = await provider.waitForTransaction(sent.hash, 1, 90000);
  assert.equal(receipt.status, 0);
  assert.equal((await registry.lookup(ref)).exists, false);
});
test('identical retry is idempotent, including when another authorized writer retries', async () => {
  const ref = reference();
  await succeeds(registry.connect(writer).record(ref, digest, txOptions));
  const original = await registry.lookup(ref);
  await succeeds(registry.setWriter(outsider.address, true, txOptions));
  assert.equal(await registry.connect(outsider).record.staticCall(ref, digest), false);
  const receipt = await succeeds(registry.connect(outsider).record(ref, digest, txOptions));
  assert.equal(receipt.logs.length, 0);
  assert.deepEqual(Array.from(await registry.lookup(ref)), Array.from(original));
});
test('conflicting overwrite is rejected on-chain and original commitment survives', async () => {
  const ref = reference();
  await succeeds(registry.connect(writer).record(ref, digest, txOptions));
  const other = sha256(toUtf8Bytes('different synthetic snapshot'));
  await reverts(registry.connect(writer).record.staticCall(ref, other), 'ConflictingCommitment');
  const sent = await registry.connect(writer).record(ref, other, { ...txOptions, gasLimit: 200000n });
  assert.equal((await provider.waitForTransaction(sent.hash, 1, 90000)).status, 0);
  assert.equal((await registry.lookup(ref)).sha256, digest);
});
test('administrator grants and revokes writers while retained history stays readable', async () => {
  await reverts(registry.connect(writer).setWriter.staticCall(outsider.address, true), 'UnauthorizedAdministrator');
  const grant = await succeeds(registry.setWriter(outsider.address, true, txOptions));
  assert.equal(registry.interface.parseLog(grant.logs[0]).name, 'WriterAuthorizationChanged');
  const ref = reference();
  await succeeds(registry.connect(outsider).record(ref, digest, txOptions));
  await succeeds(registry.setWriter(outsider.address, false, txOptions));
  await reverts(registry.connect(outsider).record.staticCall(reference(), digest), 'UnauthorizedWriter');
  assert.equal((await registry.connect(outsider).lookup(ref)).sha256, digest);
});
test('correction references append independently and never replace an earlier version', async () => {
  const original = reference(), correction = reference();
  const correctedHash = sha256(toUtf8Bytes('synthetic correction v2'));
  await succeeds(registry.connect(writer).record(original, digest, txOptions));
  await succeeds(registry.connect(writer).record(correction, correctedHash, txOptions));
  assert.equal((await registry.lookup(original)).sha256, digest);
  assert.equal((await registry.lookup(correction)).sha256, correctedHash);
});
test('zero addresses, reference and digest are rejected', async () => {
  await reverts(registry.setWriter.staticCall(ZeroAddress, true), 'ZeroAddress');
  await reverts(registry.connect(writer).record.staticCall(ZeroHash, digest), 'InvalidReference');
  await reverts(registry.connect(writer).record.staticCall(reference(), ZeroHash), 'InvalidCommitment');
  for (const args of [[ZeroAddress, writer.address], [admin.address, ZeroAddress]]) {
    const factory = new ContractFactory(artifact.abi, artifact.bytecode, admin);
    const request = await factory.getDeployTransaction(...args);
    await reverts(provider.call({ ...request, from: admin.address }), 'ZeroAddress');
  }
});
