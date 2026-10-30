import { ContractFactory, getCreateAddress, keccak256 } from 'ethers';
import { mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import { compile } from './compile.mjs';
import { chainId, txOptions, connect, signer, confirmed, verifyDeployment } from './runtime.mjs';

const directory = new URL('../deployments/', import.meta.url);
const manifest = new URL('local.json', directory);
const pending = new URL('local.pending.json', directory);
async function readOptional(path) {
  try { return JSON.parse(await readFile(path, 'utf8')); }
  catch (error) { if (error.code === 'ENOENT') return null; throw error; }
}
const artifact = await compile();
const { provider, genesisHash } = await connect();
try {
  await mkdir(directory, { recursive: true });
  const existing = await readOptional(manifest);
  if (existing) {
    await verifyDeployment(provider, artifact, existing);
    console.log(`Existing deployment verified: ${existing.address} (no transaction sent).`);
  } else {
    let intent = await readOptional(pending);
    if (intent) {
      if (intent.chainId !== chainId || intent.genesisHash !== genesisHash) throw new Error('Pending deployment belongs to another chain.');
      if (!(await provider.getTransactionReceipt(intent.transactionHash))) throw new Error('Uncertain deployment retained; inspect its transaction/nonce before any manual retry.');
    } else {
      const admin = await signer('administrator', provider);
      const writer = await signer('writer', provider);
      const nonce = await provider.getTransactionCount(admin.address, 'pending');
      const request = await new ContractFactory(artifact.abi, artifact.bytecode)
        .getDeployTransaction(admin.address, writer.address, { ...txOptions, chainId, nonce, gasLimit: 2000000n });
      const raw = await admin.signTransaction(request);
      intent = { chainId, genesisHash, address: getCreateAddress({ from: admin.address, nonce }),
        administrator: admin.address, writer: writer.address, nonce, transactionHash: keccak256(raw) };
      // Persist the hash/nonce before transport; never blindly redeploy an uncertain send.
      await writeFile(pending, JSON.stringify(intent, null, 2) + '\n', { flag: 'wx' });
      const transaction = await provider.broadcastTransaction(raw);
      await confirmed(provider, transaction);
    }
    const receipt = await provider.getTransactionReceipt(intent.transactionHash);
    if (!receipt || receipt.status !== 1) throw new Error('Deployment failed; intent retained for inspection.');
    const record = { ...intent, contract: artifact.contractName, compiler: artifact.compiler, evmVersion: artifact.evmVersion,
      runtimeCodeHash: keccak256(await provider.getCode(intent.address)), blockNumber: receipt.blockNumber,
      blockHash: receipt.blockHash, abi: '../abi/PayrollIntegrityRegistry.json' };
    await verifyDeployment(provider, artifact, record);
    await writeFile(manifest, JSON.stringify(record, null, 2) + '\n', { flag: 'wx' });
    await rm(pending);
    console.log(`Confirmed deployment: ${record.address}\nTransaction: ${record.transactionHash}\nBlock: ${record.blockNumber}`);
  }
} catch (error) {
  // Provider errors can include signed payloads. Print only a controlled summary.
  console.error(`Deployment stopped (${error.code ?? 'validation'}); inspect public transaction references and retained intent locally.`);
  process.exitCode = 1;
} finally { provider.destroy(); }
