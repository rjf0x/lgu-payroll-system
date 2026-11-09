import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { getAddress } from 'ethers';
import { RegistryStore } from '../../app/Services/Blockchain/RegistryStore.mjs';
import { RegistryBridge } from '../../app/Services/Blockchain/RegistryBridge.mjs';
import { BesuRegistryGateway } from '../../app/Services/Blockchain/BesuRegistryGateway.mjs';
import { createRegistryServer } from '../../app/Services/Blockchain/RegistryHttpServer.mjs';

function required(name, pattern) {
    const value = process.env[name];
    if (!value || (pattern && !pattern.test(value))) {
        throw new Error(`Missing or invalid ${name}`);
    }
    return value;
}

function rpcUrl(name) {
    const value = required(name);
    if (!['http:', 'https:'].includes(new URL(value).protocol)) {
        throw new Error(`Invalid ${name}`);
    }
    return value;
}

const root = fileURLToPath(new URL('../../', import.meta.url));
const config = {
    rpcUrl: rpcUrl('BESU_BRIDGE_RPC_URL'), signerUrl: rpcUrl('BESU_BRIDGE_SIGNER_URL'),
    chainId: required('BESU_BRIDGE_CHAIN_ID', /^[1-9][0-9]*$/),
    genesisHash: required('BESU_BRIDGE_GENESIS_HASH', /^0x[a-f0-9]{64}$/),
    contractAddress: getAddress(required('BESU_BRIDGE_CONTRACT_ADDRESS')),
    codeHash: required('BESU_BRIDGE_CODE_HASH', /^0x[a-f0-9]{64}$/),
    signerAddress: getAddress(required('BESU_BRIDGE_SIGNER_ADDRESS')),
    deploymentBlock: Number(required('BESU_BRIDGE_DEPLOYMENT_BLOCK', /^(0|[1-9][0-9]*)$/)),
    gasPrice: required('BESU_BRIDGE_GAS_PRICE', /^(0|[1-9][0-9]*)$/),
};
const port = Number(process.env.BESU_BRIDGE_PORT ?? '3100');
if (!Number.isInteger(port) || port < 1 || port > 65535 || !Number.isSafeInteger(config.deploymentBlock)) {
    throw new Error('Invalid bridge port or deployment block');
}
const gateway = new BesuRegistryGateway(config);
const store = new RegistryStore(resolve(root, process.env.BESU_BRIDGE_JOURNAL_FILE ?? 'storage/app/private/besu/bridge.sqlite'), {
    chainId: config.chainId, genesisHash: config.genesisHash, contractAddress: config.contractAddress,
    codeHash: config.codeHash, signerAddress: config.signerAddress, deploymentBlock: config.deploymentBlock,
});
const bridge = new RegistryBridge(store, gateway);
const server = createRegistryServer(bridge, required('BESU_BRIDGE_TOKEN'));
const interval = setInterval(() => bridge.processPending().catch(() => {
    // Do not log upstream payloads, signed bytes, credentials, or payroll metadata.
    console.error('Bridge journal processing failed; operator attention required.');
}), 5000);
server.listen(port, '127.0.0.1', () => console.log(`Besu bridge listening on loopback port ${port}`));

let stopping = false;
async function shutdown() {
    if (stopping) {
        return;
    }
    stopping = true;
    clearInterval(interval);
    await new Promise(resolveClose => server.close(resolveClose));
    await bridge.processing;
    gateway.close();
    store.close();
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
