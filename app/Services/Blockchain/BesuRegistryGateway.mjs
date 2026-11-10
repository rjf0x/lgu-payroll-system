import { AbiCoder, Contract, FetchRequest, Interface, JsonRpcProvider, Transaction, ZeroAddress, getAddress, keccak256, toQuantity } from 'ethers';
import { BridgeError } from './RegistryProtocol.mjs';

export const registryAbi = [
    'function isRegistrar(string organizationId,address registrar) view returns (bool)',
    'function registerPayroll(string organizationId,string payrollReference,uint32 revision,bytes32 payloadHash,uint32 schemaVersion) returns (bool)',
    'function getPayroll(string organizationId,string payrollReference,uint32 revision) view returns (tuple(bytes32 payloadHash,uint32 schemaVersion,uint256 registeredAt,address registrar))',
    'event PayrollRegistered(bytes32 indexed registrationKey,string organizationId,string payrollReference,uint32 revision,bytes32 payloadHash,uint32 schemaVersion,uint256 registeredAt,address indexed registrar)',
];

function provider(url) {
    const request = new FetchRequest(url);
    request.timeout = 10000;
    return new JsonRpcProvider(request, undefined, { cacheTimeout: -1, batchMaxCount: 1 });
}

export class BesuRegistryGateway {
    constructor(config) {
        this.config = config;
        this.address = getAddress(config.contractAddress);
        this.signerAddress = getAddress(config.signerAddress);
        this.rpc = provider(config.rpcUrl);
        this.signer = provider(config.signerUrl);
        this.contract = new Contract(this.address, registryAbi, this.rpc);
        this.interface = new Interface(registryAbi);
    }

    async ready() {
        const [chainId, genesis, code] = await Promise.all([
            this.rpc.send('eth_chainId', []), this.rpc.getBlock(0), this.rpc.getCode(this.address),
        ]);
        if (BigInt(chainId) !== BigInt(this.config.chainId) || genesis?.hash?.toLowerCase() !== this.config.genesisHash
            || code === '0x' || keccak256(code) !== this.config.codeHash) {
            throw new BridgeError('NETWORK_OR_CONTRACT_MISMATCH');
        }
    }

    async pendingNonce() {
        return this.rpc.getTransactionCount(this.signerAddress, 'pending');
    }

    async registrarAuthorized(organization) {
        if (typeof organization !== 'string' || !/^[a-z0-9][a-z0-9-]{0,62}$/.test(organization)) {
            throw new BridgeError('INVALID_IDENTITY', 422);
        }
        await this.ready();
        return this.contract.isRegistrar(organization, this.signerAddress);
    }

    async nodeStatus() {
        const [block, syncing] = await Promise.all([this.rpc.getBlock('latest'), this.rpc.send('eth_syncing', [])]);
        if (!block) {
            throw new BridgeError('NODE_UNAVAILABLE');
        }
        return { block_number: block.number, block_timestamp: block.timestamp, syncing: syncing !== false };
    }

    async health() {
        await this.ready();
        const accounts = await this.signer.send('eth_accounts', []);
        if (!Array.isArray(accounts) || !accounts.some(account => typeof account === 'string' && account.toLowerCase() === this.signerAddress.toLowerCase())) {
            throw new BridgeError('SIGNER_UNAVAILABLE');
        }
        return this.networkIdentity();
    }

    networkIdentity() {
        return { chain_id: String(this.config.chainId), genesis_hash: this.config.genesisHash,
            contract_address: this.address.toLowerCase(), code_hash: this.config.codeHash,
            signer_address: this.signerAddress.toLowerCase(), deployment_block: this.config.deploymentBlock };
    }

    async prepare(record, nonce) {
        if (!await this.contract.isRegistrar(record.organization_id, this.signerAddress)) {
            throw new BridgeError('SIGNER_NOT_AUTHORIZED');
        }
        const data = this.interface.encodeFunctionData('registerPayroll', [record.organization_id, record.payroll_reference,
            record.revision, `0x${record.payload_hash}`, record.hash_schema_version]);
        const gas = await this.rpc.estimateGas({ from: this.signerAddress, to: this.address, data });
        return { from: this.signerAddress, to: this.address, data, chainId: toQuantity(this.config.chainId),
            nonce: toQuantity(nonce), gas: toQuantity(gas * 12n / 10n), gasPrice: toQuantity(this.config.gasPrice), value: '0x0' };
    }

    async sign(envelope) {
        // Web3Signer signs without broadcasting; the journal owns the broadcast boundary.
        const raw = await this.signer.send('eth_signTransaction', [envelope]);
        let transaction;
        try {
            transaction = Transaction.from(raw);
        } catch {
            throw new BridgeError('INVALID_SIGNER_RESPONSE');
        }
        if (!transaction.isSigned() || transaction.from !== this.signerAddress || transaction.to !== this.address
            || transaction.chainId !== BigInt(envelope.chainId) || transaction.nonce !== Number(BigInt(envelope.nonce))
            || transaction.data !== envelope.data || transaction.value !== 0n || transaction.type !== 0
            || transaction.gasLimit !== BigInt(envelope.gas) || transaction.gasPrice !== BigInt(envelope.gasPrice)) {
            throw new BridgeError('INVALID_SIGNER_RESPONSE');
        }
        return { raw, hash: transaction.hash };
    }

    async broadcast(raw, expectedHash) {
        try {
            const hash = await this.rpc.send('eth_sendRawTransaction', [raw]);
            if (hash?.toLowerCase() !== expectedHash.toLowerCase()) {
                throw new BridgeError('INVALID_BROADCAST_RESPONSE');
            }
        } catch (error) {
            // Confirm already-known responses against the exact hash, never message text alone.
            const known = await this.rpc.getTransaction(expectedHash);
            const receipt = known?.hash?.toLowerCase() === expectedHash.toLowerCase()
                ? null : await this.rpc.getTransactionReceipt(expectedHash);
            if (known?.hash?.toLowerCase() === expectedHash.toLowerCase() || receipt?.hash?.toLowerCase() === expectedHash.toLowerCase()) {
                return;
            }
            throw error;
        }
    }

    async receipt(hash) {
        const receipt = await this.rpc.getTransactionReceipt(hash);
        if (!receipt) {
            return null;
        }
        const [block, transaction] = await Promise.all([this.rpc.getBlock(receipt.blockNumber), this.rpc.getTransaction(hash)]);
        if (!block || block.hash !== receipt.blockHash || receipt.hash.toLowerCase() !== hash.toLowerCase()
            || getAddress(receipt.to) !== this.address || !transaction || transaction.blockHash !== receipt.blockHash
            || transaction.hash?.toLowerCase() !== hash.toLowerCase() || getAddress(transaction.to) !== this.address
            || transaction.chainId !== BigInt(this.config.chainId) || ![0, 1].includes(receipt.status)) {
            throw new BridgeError('INVALID_EVIDENCE');
        }
        return { transaction_hash: hash, block_number: receipt.blockNumber, block_hash: receipt.blockHash,
            successful: receipt.status === 1, receipt, transaction, block };
    }

    async registration(record) {
        const stored = await this.contract.getPayroll(record.organization_id, record.payroll_reference, record.revision);
        if (stored.registrar === ZeroAddress) {
            return null;
        }
        const key = keccak256(AbiCoder.defaultAbiCoder().encode(['string', 'string', 'uint32'],
            [record.organization_id, record.payroll_reference, record.revision]));
        const latestBlock = await this.rpc.getBlockNumber();
        const logs = [];
        for (let fromBlock = this.config.deploymentBlock; fromBlock <= latestBlock; fromBlock += 1000) {
            logs.push(...await this.rpc.getLogs({ address: this.address, fromBlock, toBlock: Math.min(fromBlock + 999, latestBlock),
                topics: [this.interface.getEvent('PayrollRegistered').topicHash, key] }));
        }
        if (logs.length !== 1 || logs[0].removed) {
            throw new BridgeError('INVALID_EVIDENCE');
        }
        const event = this.interface.parseLog(logs[0]).args;
        const evidence = await this.receipt(logs[0].transactionHash);
        if (!evidence?.successful || evidence.block_hash !== logs[0].blockHash
            || !evidence.receipt.logs.some(log => log.index === logs[0].index && log.address.toLowerCase() === this.address.toLowerCase()
                && log.data === logs[0].data && JSON.stringify(log.topics) === JSON.stringify(logs[0].topics))
            || event.registrationKey !== key || event.organizationId !== record.organization_id || event.payrollReference !== record.payroll_reference
            || event.revision !== BigInt(record.revision) || event.payloadHash !== stored.payloadHash
            || event.schemaVersion !== stored.schemaVersion || event.registeredAt !== stored.registeredAt
            || event.registrar !== stored.registrar || evidence.transaction.from !== stored.registrar
            || BigInt(evidence.block.timestamp) !== stored.registeredAt) {
            throw new BridgeError('INVALID_EVIDENCE');
        }
        let call;
        try {
            call = this.interface.parseTransaction({ data: evidence.transaction.data });
        } catch {
            throw new BridgeError('INVALID_EVIDENCE');
        }
        if (call?.name !== 'registerPayroll' || call.args[0] !== record.organization_id || call.args[1] !== record.payroll_reference
            || call.args[2] !== BigInt(record.revision) || call.args[3] !== stored.payloadHash || call.args[4] !== stored.schemaVersion) {
            throw new BridgeError('INVALID_EVIDENCE');
        }
        return { ...recordIdentity(record), payload_hash: stored.payloadHash.slice(2), hash_schema_version: Number(stored.schemaVersion),
            registered_at: stored.registeredAt.toString(), registrar: stored.registrar, transaction_hash: evidence.transaction_hash,
            block_number: evidence.block_number, block_hash: evidence.block_hash, chain_id: this.config.chainId,
            genesis_hash: this.config.genesisHash, contract_address: this.address.toLowerCase() };
    }

    close() {
        this.rpc.destroy();
        this.signer.destroy();
    }
}

function recordIdentity(record) {
    return { organization_id: record.organization_id, payroll_reference: record.payroll_reference, revision: record.revision };
}
