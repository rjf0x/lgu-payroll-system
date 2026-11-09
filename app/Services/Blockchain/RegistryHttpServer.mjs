import { createServer } from 'node:http';
import { createHash, timingSafeEqual } from 'node:crypto';
import { BridgeError, identity } from './RegistryProtocol.mjs';

export function createRegistryServer(bridge, token) {
    if (typeof token !== 'string' || Buffer.byteLength(token) < 32) {
        throw new BridgeError('TOKEN_MUST_HAVE_AT_LEAST_32_BYTES');
    }
    const expectedToken = createHash('sha256').update(`Bearer ${token}`).digest();
    const server = createServer(async (request, response) => {
        const send = (status, data) => {
            response.writeHead(status, { 'content-type': 'application/json', 'cache-control': 'no-store' });
            response.end(JSON.stringify(data));
        };
        try {
            const actualToken = createHash('sha256').update(request.headers.authorization ?? '').digest();
            if (!timingSafeEqual(expectedToken, actualToken)) {
                throw new BridgeError('UNAUTHORIZED', 401);
            }
            const url = new URL(request.url, 'http://bridge.invalid');
            if (url.search) {
                throw new BridgeError('UNEXPECTED_QUERY', 422);
            }
            if (request.method === 'GET' && url.pathname === '/health') {
                await bridge.gateway.health();
                return send(200, { status: 'READY' });
            }
            if (request.method === 'POST' && url.pathname === '/registrations') {
                if (!/^application\/json(?:\s*;.*)?$/i.test(request.headers['content-type'] ?? '')) {
                    throw new BridgeError('JSON_REQUIRED', 415);
                }
                const chunks = [];
                let size = 0;
                for await (const chunk of request) {
                    size += chunk.length;
                    if (size > 4096) {
                        throw new BridgeError('REQUEST_TOO_LARGE', 413);
                    }
                    chunks.push(chunk);
                }
                let input;
                try {
                    input = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(Buffer.concat(chunks)));
                } catch {
                    throw new BridgeError('INVALID_JSON', 400);
                }
                return send(202, bridge.enqueue(input));
            }
            const registration = /^\/registrations\/([^/]+)\/([^/]+)\/([1-9][0-9]*)$/.exec(url.pathname);
            if (request.method === 'GET' && registration) {
                const subject = identity(registration[1], registration[2], Number(registration[3]));
                return send(200, await bridge.read(subject));
            }
            const transaction = /^\/transactions\/(0x[a-fA-F0-9]{64})$/.exec(url.pathname);
            if (request.method === 'GET' && transaction) {
                await bridge.gateway.ready();
                const receipt = await bridge.gateway.receipt(transaction[1]);
                return send(200, receipt ? { transaction_hash: receipt.transaction_hash, block_number: receipt.block_number,
                    block_hash: receipt.block_hash, successful: receipt.successful } : { status: 'PENDING_OR_UNKNOWN' });
            }
            throw new BridgeError('NOT_FOUND', 404);
        } catch (error) {
            send(error instanceof BridgeError ? error.status : 503, {
                error: error instanceof BridgeError ? error.code : 'UPSTREAM_UNAVAILABLE',
            });
        }
    });
    server.requestTimeout = 10000;
    server.headersTimeout = 5000;
    server.keepAliveTimeout = 5000;
    server.maxRequestsPerSocket = 100;
    return server;
}
