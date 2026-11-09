import { createHash } from 'node:crypto';

export class BridgeError extends Error {
    constructor(code, status = 503) {
        super(code);
        this.code = code;
        this.status = status;
    }
}

export function identity(organization_id, payroll_reference, revision) {
    if (typeof organization_id !== 'string' || typeof payroll_reference !== 'string'
        || !/^[a-z0-9][a-z0-9-]{0,62}$/.test(organization_id)
        || !/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(payroll_reference ?? '')
        || !Number.isSafeInteger(revision) || revision < 1 || revision > 4294967295) {
        throw new BridgeError('INVALID_IDENTITY', 422);
    }
    return { organization_id, payroll_reference, revision };
}

export function idempotencyKey(value) {
    return createHash('sha256').update(`${value.organization_id}\n${value.payroll_reference}\n${value.revision}`, 'utf8').digest('hex');
}

export function registrationRequest(input) {
    const keys = ['organization_id', 'payroll_reference', 'revision', 'payload_hash', 'hash_schema_version', 'idempotency_key'];
    if (!input || Array.isArray(input) || Object.keys(input).length !== keys.length || keys.some(key => !Object.hasOwn(input, key))) {
        throw new BridgeError('INVALID_REQUEST', 422);
    }
    const result = identity(input.organization_id, input.payroll_reference, input.revision);
    if (typeof input.payload_hash !== 'string' || !/^[a-f0-9]{64}$/.test(input.payload_hash)
        || /^0+$/.test(input.payload_hash) || input.hash_schema_version !== 1
        || input.idempotency_key !== idempotencyKey(result)) {
        throw new BridgeError('INVALID_REQUEST', 422);
    }
    return { ...result, payload_hash: input.payload_hash, hash_schema_version: 1, idempotency_key: input.idempotency_key };
}

export function publicRecord(record) {
    const { raw_transaction, envelope, ...result } = record;
    return result;
}
