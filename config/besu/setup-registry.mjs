import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import { Contract, FetchRequest, Interface, JsonRpcProvider, Transaction, getAddress, getCreateAddress, keccak256, toQuantity } from 'ethers';
import { RegistryStore } from '../../app/Services/Blockchain/RegistryStore.mjs';

// Development-only bootstrap. Production deployments require a separately managed administrator.
const root = fileURLToPath(new URL('../../', import.meta.url));
const state = resolve(root, 'storage/app/private/besu');
const artifacts = resolve(state, 'registry-contract');
mkdirSync(artifacts, { recursive: true });
execFileSync('docker', ['run', '--rm', '--mount', `type=bind,source=${root},target=/source,readonly`,
    '--mount', `type=bind,source=${artifacts},target=/output`, 'ethereum/solc:0.8.30', '--base-path', '/source',
    '--evm-version', 'london', '--optimize', '--bin', '--abi', '--overwrite', '-o', '/output',
    '/source/app/Services/Blockchain/PayrollIntegrityRegistry.sol'], { stdio: 'inherit', windowsHide: true });
const bytecode = `0x${readFileSync(resolve(artifacts, 'PayrollIntegrityRegistry.bin'), 'utf8').trim()}`;
const abi = JSON.parse(readFileSync(resolve(artifacts, 'PayrollIntegrityRegistry.abi'), 'utf8'));
const rpcUrl = 'http://127.0.0.1:28545';
const signerUrl = 'http://127.0.0.1:29000';
function provider(url) {
    const request = new FetchRequest(url);
    request.timeout = 10000;
    return new JsonRpcProvider(request, undefined, { cacheTimeout: -1, batchMaxCount: 1 });
}
const rpc = provider(rpcUrl);
const signer = provider(signerUrl);
let store;
try {
    const chain = await rpc.send('eth_chainId', []);
    const genesis = await rpc.getBlock(0);
    const latest = await rpc.getBlock('latest');
    const validators = await rpc.send('qbft_getValidatorsByBlockNumber', ['latest']);
    const accounts = await signer.send('eth_accounts', []);
    if (BigInt(chain) !== 1337n || latest.baseFeePerGas !== 0n || validators.length !== 4 || accounts.length !== 1) {
        throw new Error('Expected the repository local zero-fee chain 1337, four QBFT validators and one signer.');
    }
    const administrator = getAddress(accounts[0]);
    store = new RegistryStore(resolve(state, 'registry-deployment.sqlite'), {
        chainId: '1337', genesisHash: genesis.hash, administrator, creationHash: keccak256(bytecode),
    });

    async function sendOperation(key, to, data) {
        let operation = store.get(key);
        if (!operation) {
            const nonce = Math.max(await rpc.getTransactionCount(administrator, 'pending'),
                ...store.all().map(row => row.nonce + 1));
            const gas = await rpc.estimateGas({ from: administrator, to, data });
            const envelope = { from: administrator, data, nonce: toQuantity(nonce), chainId: '0x539', gasPrice: '0x0',
                gas: toQuantity(gas * 12n / 10n), value: '0x0', ...(to ? { to } : {}) };
            operation = { idempotency_key: key, nonce, envelope };
            store.save(operation);
        }
        if (!operation.raw_transaction) {
            const raw = await signer.send('eth_signTransaction', [operation.envelope]);
            const parsed = Transaction.from(raw);
            if (!parsed.isSigned() || parsed.from !== administrator || parsed.to !== to || parsed.chainId !== 1337n
                || parsed.nonce !== operation.nonce || parsed.data !== data || parsed.value !== 0n || parsed.type !== 0
                || parsed.gasPrice !== 0n || parsed.gasLimit !== BigInt(operation.envelope.gas)) {
                throw new Error('Signer response does not match the deployment operation.');
            }
            operation.raw_transaction = raw;
            operation.transaction_hash = parsed.hash;
            store.save(operation);
        }
        let receipt = await rpc.getTransactionReceipt(operation.transaction_hash);
        if (!receipt) {
            try {
                const submitted = await rpc.send('eth_sendRawTransaction', [operation.raw_transaction]);
                if (submitted !== operation.transaction_hash) {
                    throw new Error('Unexpected transaction hash.');
                }
            } catch {
                if (!await rpc.getTransaction(operation.transaction_hash) && !await rpc.getTransactionReceipt(operation.transaction_hash)) {
                    throw new Error('Submission is ambiguous. The signed operation is saved; rerun to reconcile it.');
                }
            }
            for (let attempt = 0; attempt < 40; attempt++) {
                receipt = await rpc.getTransactionReceipt(operation.transaction_hash);
                if (receipt) {
                    break;
                }
                await delay(500);
            }
        }
        if (!receipt || receipt.status !== 1 || (await rpc.getBlock(receipt.blockNumber))?.hash !== receipt.blockHash) {
            throw new Error('Operation is pending, reverted, or not canonical. Preserve the journal and inspect before retrying.');
        }
        return { operation, receipt };
    }

    const deployment = await sendOperation('deploy-registry-v1', null, bytecode);
    const contractAddress = getCreateAddress({ from: administrator, nonce: deployment.operation.nonce });
    if (deployment.receipt.contractAddress !== contractAddress) {
        throw new Error('Unexpected deployed registry address.');
    }
    const contract = new Contract(contractAddress, abi, rpc);
    if (await contract.administrator() !== administrator) {
        throw new Error('The registry administrator is not the local signer.');
    }
    const permission = new Interface(abi).encodeFunctionData('setRegistrar', ['lgu-pilot', administrator, true]);
    await sendOperation('authorize-lgu-pilot', contractAddress, permission);
    if (!await contract.isRegistrar('lgu-pilot', administrator)) {
        throw new Error('Pilot registrar permission was not recorded.');
    }
    const metadata = { rpcUrl, signerUrl, chainId: '1337', genesisHash: genesis.hash.toLowerCase(),
        contractAddress, codeHash: keccak256(await rpc.getCode(contractAddress)), signerAddress: administrator,
        deploymentBlock: deployment.receipt.blockNumber, gasPrice: '0', organizationId: 'lgu-pilot',
        validatorCount: validators.length, deploymentTransaction: deployment.operation.transaction_hash };
    writeFileSync(resolve(state, 'registry-deployment.json'), JSON.stringify(metadata, null, 2)+'\n', { mode: 0o600 });
    console.log(`Local registry ready: ${contractAddress}. Public metadata saved; application configuration was not changed.`);
} finally {
    store?.close();
    rpc.destroy();
    signer.destroy();
}
