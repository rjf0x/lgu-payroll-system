import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/header.js', import.meta.url), 'utf8');
const connected = { connected: true, scope: 'node_connectivity' };
const flush = () => new Promise(setImmediate);

async function header({ status = 200, payload = connected, hidden = false, reply } = {}) {
    const label = { textContent: '' };
    const classes = new Set(['bg-slate-400']);
    const dot = { classList: {
        add: (...names) => names.forEach((name) => classes.add(name)),
        remove: (...names) => names.forEach((name) => classes.delete(name)),
    } };
    const widget = {
        dataset: { statusUrl: '/blockchain/status' },
        querySelector: (selector) => selector === '[data-besu-label]' ? label : dot,
    };
    const requests = [];
    const intervals = [];
    const listeners = new Map();
    const document = {
        hidden,
        getElementById: (id) => id === 'besu-status' ? widget : null,
        addEventListener: (name, callback) => listeners.set(name, callback),
    };
    const fetch = async (url, options) => {
        requests.push({ url, options });

        return reply ? reply() : { status, json: async () => payload };
    };
    const window = { setInterval: (callback, delay) => intervals.push({ callback, delay }) };

    vm.runInNewContext(source, { document, window, fetch, AbortSignal });
    await flush();

    return { label, classes, requests, intervals, listeners, document };
}

test('a responding node reports connectivity only and polls with uncached same-origin requests', async () => {
    const state = await header();

    assert.equal(state.label.textContent, 'Node connected');
    assert.equal(state.classes.has('bg-emerald-600'), true);
    assert.equal(state.requests.length, 1);
    assert.equal(state.requests[0].url, '/blockchain/status');
    assert.equal(state.requests[0].options.cache, 'no-store');
    assert.equal(state.requests[0].options.credentials, 'same-origin');
    assert.equal(state.requests[0].options.headers.Accept, 'application/json');
    assert.equal(state.intervals[0].delay, 30000);
});

test('a node outage is distinguished from unavailable session or response evidence', async () => {
    const state = await header({ status: 503, payload: { connected: false, scope: 'node_connectivity' } });

    assert.equal(state.label.textContent, 'Node unavailable');
    assert.equal(state.classes.has('bg-red-600'), true);
    assert.equal(state.classes.has('bg-emerald-600'), false);
});

for (const [name, options] of [
    ['expired session', { status: 401 }],
    ['inactive session', { status: 403 }],
    ['server error', { status: 500 }],
    ['missing node scope', { payload: { connected: true } }],
    ['invalid connected flag', { payload: { connected: 'true', scope: 'node_connectivity' } }],
    ['contradictory success', { status: 200, payload: { connected: false, scope: 'node_connectivity' } }],
    ['contradictory outage', { status: 503, payload: connected }],
    ['invalid json', { reply: () => ({ status: 200, json: async () => { throw new Error('Invalid JSON'); } }) }],
    ['network timeout', { reply: () => { throw new Error('Connection unavailable'); } }],
]) {
    test(`${name} cannot display a connected node`, async () => {
        const state = await header(options);

        assert.equal(state.label.textContent, 'Node status unavailable');
        assert.equal(state.classes.has('bg-slate-400'), true);
        assert.equal(state.classes.has('bg-emerald-600'), false);
    });
}

test('a session denial clears a previously connected badge', async () => {
    let calls = 0;
    const state = await header({ reply: () => ({ status: ++calls === 1 ? 200 : 403, json: async () => connected }) });
    assert.equal(state.label.textContent, 'Node connected');

    await state.intervals[0].callback();

    assert.equal(state.label.textContent, 'Node status unavailable');
    assert.equal(state.classes.has('bg-emerald-600'), false);
});

test('hidden pages wait until visible before polling the node', async () => {
    const state = await header({ hidden: true });
    assert.equal(state.requests.length, 0);

    state.document.hidden = false;
    state.listeners.get('visibilitychange')();
    await flush();

    assert.equal(state.requests.length, 1);
    assert.equal(state.label.textContent, 'Node connected');
});

test('an in-flight node check prevents overlapping polls', async () => {
    let resolve;
    const pending = new Promise((done) => { resolve = done; });
    const state = await header({ reply: () => pending });
    await state.intervals[0].callback();
    assert.equal(state.requests.length, 1);

    resolve({ status: 200, json: async () => connected });
    await flush();

    assert.equal(state.label.textContent, 'Node connected');
});
