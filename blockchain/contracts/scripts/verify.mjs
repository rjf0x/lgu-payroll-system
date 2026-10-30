import { readFile } from 'node:fs/promises';
import { compile } from './compile.mjs';
import { connect, verifyDeployment } from './runtime.mjs';

const artifact = await compile();
const record = JSON.parse(await readFile(new URL('../deployments/local.json', import.meta.url), 'utf8'));
const { provider } = await connect();
try {
  const { receipt } = await verifyDeployment(provider, artifact, record);
  console.log(`Verified registry ${record.address}, chain ${record.chainId}, successful canonical deployment block ${receipt.blockNumber}.`);
} finally { provider.destroy(); }
