import { JsonRpcProvider, FetchRequest, Wallet, ContractFactory, Contract, keccak256 } from 'ethers';
import { readFile } from 'node:fs/promises';

export const chainId = 20261030;
export const txOptions = { type: 0, gasPrice: 0n };
export async function connect() {
  const endpoint = process.env.BESU_RPC_URL ?? 'http://127.0.0.1:18545';
  const url = new URL(endpoint);
  if (url.protocol !== 'http:' || !['127.0.0.1', 'localhost'].includes(url.hostname) || url.username || url.password) {
    throw new Error('BESU-02 tooling permits only local HTTP RPC without URL credentials.');
  }
  const request = new FetchRequest(endpoint);
  request.timeout = 5000;
  const provider = new JsonRpcProvider(request, undefined, { cacheTimeout: -1 });
  provider.pollingInterval = 500;
  try {
    if (Number((await provider.getNetwork()).chainId) !== chainId) throw new Error('Unexpected chain ID.');
    if (!(await provider.send('web3_clientVersion', [])).startsWith('besu/')) throw new Error('Expected real Besu client.');
    const genesis = await provider.getBlock(0);
    return { provider, genesisHash: genesis.hash };
  } catch (error) { provider.destroy(); throw error; }
}
export async function signer(role, provider) {
  if (!['administrator', 'writer', 'outsider'].includes(role)) throw new Error('Unknown development role.');
  const [json, password] = await Promise.all([
    readFile(new URL(`../private/${role}.json`, import.meta.url), 'utf8'),
    readFile(new URL(`../private/${role}.password`, import.meta.url), 'utf8')
  ]);
  return (await Wallet.fromEncryptedJson(json, password)).connect(provider);
}
export async function confirmed(provider, transaction) {
  const receipt = await provider.waitForTransaction(transaction.hash, 1, 90000);
  if (!receipt || receipt.status !== 1) throw new Error('Missing or failed confirmed receipt.');
  if ((await provider.getBlock(receipt.blockNumber)).hash !== receipt.blockHash) throw new Error('Receipt is not canonical.');
  return receipt;
}
export async function deployFixture(artifact, admin, writer) {
  const contract = await new ContractFactory(artifact.abi, artifact.bytecode, admin)
    .deploy(admin.address, writer.address, txOptions);
  const receipt = await confirmed(admin.provider, contract.deploymentTransaction());
  return { contract, receipt };
}
export async function verifyDeployment(provider, artifact, record) {
  if (record.chainId !== chainId || (await provider.getBlock(0)).hash !== record.genesisHash) throw new Error('Deployment chain identity mismatch.');
  const receipt = await provider.getTransactionReceipt(record.transactionHash);
  if (!receipt || receipt.status !== 1 || receipt.contractAddress !== record.address || receipt.from !== record.administrator) throw new Error('Invalid deployment receipt.');
  if ((await provider.getBlock(receipt.blockNumber)).hash !== receipt.blockHash) throw new Error('Noncanonical deployment.');
  const transaction = await provider.getTransaction(record.transactionHash);
  const creation = await new ContractFactory(artifact.abi, artifact.bytecode).getDeployTransaction(record.administrator, record.writer);
  if (!transaction || transaction.to !== null || transaction.data !== creation.data) throw new Error('Deployment does not match compiled source/constructor.');
  const code = await provider.getCode(record.address);
  if (code === '0x' || keccak256(code) !== record.runtimeCodeHash) throw new Error('Runtime code mismatch.');
  const contract = new Contract(record.address, artifact.abi, provider);
  if (await contract.administrator() !== record.administrator || !(await contract.writers(record.writer))) throw new Error('Registry authorization mismatch.');
  return { contract, receipt };
}
