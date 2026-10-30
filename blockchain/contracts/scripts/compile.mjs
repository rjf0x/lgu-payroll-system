import solc from 'solc';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

export const root = fileURLToPath(new URL('../', import.meta.url));
export async function compile() {
  const source = await readFile(new URL('../src/PayrollIntegrityRegistry.sol', import.meta.url), 'utf8');
  const settings = { optimizer: { enabled: true, runs: 200 }, evmVersion: 'shanghai',
    outputSelection: { '*': { '*': ['abi', 'evm.bytecode.object', 'evm.deployedBytecode.object'] } } };
  const output = JSON.parse(solc.compile(JSON.stringify({ language: 'Solidity',
    sources: { 'PayrollIntegrityRegistry.sol': { content: source } }, settings })));
  if (output.errors?.length) throw new Error(output.errors.map(e => e.formattedMessage).join('\n'));
  const contract = output.contracts['PayrollIntegrityRegistry.sol'].PayrollIntegrityRegistry;
  return { contractName: 'PayrollIntegrityRegistry', compiler: solc.version(), evmVersion: 'shanghai',
    abi: contract.abi, bytecode: `0x${contract.evm.bytecode.object}`,
    deployedBytecode: `0x${contract.evm.deployedBytecode.object}` };
}
if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const artifact = await compile();
  await mkdir(new URL('../artifacts/', import.meta.url), { recursive: true });
  await mkdir(new URL('../abi/', import.meta.url), { recursive: true });
  await writeFile(new URL('../artifacts/PayrollIntegrityRegistry.json', import.meta.url), JSON.stringify(artifact, null, 2) + '\n');
  await writeFile(new URL('../abi/PayrollIntegrityRegistry.json', import.meta.url), JSON.stringify(artifact.abi, null, 2) + '\n');
  console.log(`Compiled ${artifact.contractName} with ${artifact.compiler}, EVM ${artifact.evmVersion}`);
}
