import { BridgeError, idempotencyKey, publicRecord, registrationRequest } from './RegistryProtocol.mjs';

export class RegistryBridge {
    constructor(store, gateway, now = Date.now) {
        this.store = store;
        this.gateway = gateway;
        this.now = now;
        this.processing = null;
    }

    enqueue(input) {
        return publicRecord(this.store.enqueue(registrationRequest(input)));
    }

    processPending() {
        this.processing ??= this.drain().finally(() => { this.processing = null; });
        return this.processing;
    }

    async drain() {
        for (const record of this.store.all()) {
            if (['CONFIRMED', 'FAILED', 'CONFLICT'].includes(record.status)) {
                continue;
            }
            if (record.next_attempt_at && record.next_attempt_at > this.now()) {
                break;
            }
            try {
                if (await this.process(record) === false) {
                    break;
                }
            } catch (error) {
                // A known signed transaction always retains its identity for exact-byte replay.
                record.status = record.raw_transaction ? 'RECONCILING' : 'QUEUED';
                record.last_error = error instanceof BridgeError ? error.code : 'UPSTREAM_UNAVAILABLE';
                record.retry_count++;
                record.next_attempt_at = this.now() + Math.min(300000, 1000 * 2 ** Math.min(record.retry_count, 9));
                this.store.save(record);
                // Preserve nonce ordering when signing or broadcasting an earlier request is unresolved.
                break;
            }
        }
    }

    async process(record) {
        await this.gateway.ready();
        const onChain = await this.gateway.registration(record);
        if (onChain) {
            const matches = onChain.payload_hash === record.payload_hash && onChain.hash_schema_version === record.hash_schema_version;
            if (record.raw_transaction && !await this.gateway.receipt(record.transaction_hash)) {
                // Another registrar can win the race while our signed nonce is still unresolved.
                // Never abandon that nonce and allocate later ones across a potential gap.
                if (matches) {
                    await this.gateway.broadcast(record.raw_transaction, record.transaction_hash);
                }
                record.status = 'RECONCILING';
                record.last_error = matches ? 'AWAITING_OWN_TRANSACTION' : 'CONFLICT_WITH_UNRESOLVED_NONCE';
                record.evidence = onChain;
                this.store.save(record);
                return false;
            }
            if (!record.raw_transaction) {
                // A saved unsigned envelope is safe to release: eth_signTransaction never broadcasts.
                record.nonce = null;
                delete record.envelope;
            }
            record.status = matches ? 'CONFIRMED' : 'CONFLICT';
            record.evidence = onChain;
            record.last_error = record.status === 'CONFLICT' ? 'REGISTRATION_CONFLICT' : null;
            this.store.save(record);
            return;
        }

        if (record.transaction_hash) {
            const receipt = await this.gateway.receipt(record.transaction_hash);
            if (receipt) {
                if (!receipt.successful) {
                    record.status = 'FAILED';
                    record.evidence = { transaction_hash: receipt.transaction_hash, block_number: receipt.block_number,
                        block_hash: receipt.block_hash, successful: false };
                    record.last_error = 'TRANSACTION_REVERTED';
                    this.store.save(record);
                    return;
                }
                throw new BridgeError('INVALID_EVIDENCE');
            }
        }

        if (!record.raw_transaction) {
            if (!record.envelope) {
                const savedNonces = this.store.all().filter(item => item.nonce !== null).map(item => item.nonce);
                record.nonce = Math.max(await this.gateway.pendingNonce(), ...savedNonces.map(value => value + 1));
                record.envelope = await this.gateway.prepare(record, record.nonce);
                this.store.save(record);
            }
            const signed = await this.gateway.sign(record.envelope);
            record.raw_transaction = signed.raw;
            record.transaction_hash = signed.hash;
            record.status = 'RECONCILING';
            this.store.save(record);
        }

        // Persist intent before the only operation that can broadcast. Retries reuse signed bytes.
        record.status = 'RECONCILING';
        record.attempts++;
        this.store.save(record);
        await this.gateway.broadcast(record.raw_transaction, record.transaction_hash);
        record.status = 'SUBMITTED';
        record.last_error = null;
        this.store.save(record);
    }

    async read(identity) {
        await this.gateway.ready();
        const evidence = await this.gateway.registration(identity);
        const local = this.store.get(idempotencyKey(identity));
        return { registration: evidence, submission: local ? publicRecord(local) : null };
    }
}
