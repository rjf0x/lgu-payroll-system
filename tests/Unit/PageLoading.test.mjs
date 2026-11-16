import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initializePageLoading, isPageDestination, pageKind } from '../../resources/js/page-loading.js';

function fixture() {
    const timers = new Map();
    const listeners = new Map();
    let clock = 0;
    let timerId = 0;
    const status = { textContent: '' };
    const preview = { replaceChildren(...children) { this.children = children; } };
    const dismiss = { addEventListener(name, callback) { this[name] = callback; } };
    const content = { hidden: false, inert: false, attributes: new Map(),
        setAttribute(name, value) { this.attributes.set(name, value); },
        removeAttribute(name) { this.attributes.delete(name); } };
    const focus = { calls: 0, focus() { this.calls++; } };
    const loader = { hidden: true, dataset: { currentKind: 'form', dashboardUrl: 'https://payroll.test/dashboard' },
        querySelector(selector) {
            if (selector === '[data-page-loading-preview]') { return preview; }
            if (selector === '[data-page-loading-status]') { return status; }
            if (selector === '[data-page-loading-dismiss]') { return dismiss; }
            const kind = /data-loading-template="(\w+)"/.exec(selector)?.[1];
            return kind ? { content: { cloneNode: () => kind } } : null;
        } };
    const doc = { activeElement: focus,
        querySelector: selector => selector === '[data-page-loader]' ? loader : content };
    const browser = { location: { href: 'https://payroll.test/employees/create' }, scrollY: 200,
        addEventListener(name, callback) { listeners.set(name, callback); },
        setTimeout(callback, delay) { timers.set(++timerId, { callback, at: clock + delay }); return timerId; },
        clearTimeout(id) { timers.delete(id); },
        scrollTo({ top }) { this.scrollY = top; } };
    initializePageLoading(doc, browser);
    return { loader, content, preview, status, dismiss, focus, browser,
        emit(name, event = {}) { listeners.get(name)(event); },
        advance(ms) {
            clock += ms;
            for (const [id, timer] of timers) {
                if (timer.at <= clock) { timers.delete(id); timer.callback(); }
            }
        } };
}

function element(attributes = {}) {
    return { ...attributes,
        hasAttribute: name => Object.hasOwn(attributes, name),
        getAttribute: name => attributes[name] ?? null };
}

function click(href, attributes = {}, modifiers = {}) {
    const link = element({ href, ...attributes });
    return { button: 0, target: { closest: () => link }, ...modifiers };
}

test('selects skeletons for dashboard, lists, forms, details, reports and sign in under a base path', () => {
    const dashboard = 'https://payroll.test/staff/dashboard';
    for (const [path, kind] of [
        ['dashboard', 'dashboard'], ['employees?q=test', 'table'], ['employees/create', 'form'],
        ['employees/12/edit', 'form'], ['employees/12', 'detail'], ['system-settings', 'form'],
        ['payroll-runs/1/details/2/edit', 'form'], ['reports', 'report'],
        ['payroll-runs/1/summary', 'report'], ['payroll-runs/1/register', 'report'],
        ['payslips/9', 'report'], ['payroll-runs/1/payslips', 'table'], ['login', 'auth'],
    ]) {
        assert.equal(pageKind(new URL(`https://payroll.test/staff/${path}`), dashboard), kind);
    }
});

test('keeps downloads, external URLs and in-page navigation out of page loading', () => {
    const current = new URL('https://payroll.test/employees?page=2');
    for (const href of ['mailto:help@example.test', 'https://other.test/employees', '/audit-logs/export',
        '/reports/1/download', '/payroll-runs/1/register/export', '/file.pdf', '/employees?page=2#results']) {
        assert.equal(isPageDestination(new URL(href, current), current), false, href);
    }
    assert.equal(isPageDestination(new URL('/employees?page=3', current), current), true);
});

test('shows the destination skeleton only after a delay and restores content on browser Back', () => {
    const f = fixture();
    f.emit('click', click('https://payroll.test/dashboard'));
    f.advance(179);
    assert.equal(f.loader.hidden, true);
    assert.equal(f.content.hidden, false);
    f.advance(1);
    assert.deepEqual(f.preview.children, ['dashboard']);
    assert.equal(f.content.hidden, true);
    assert.equal(f.content.inert, true);
    assert.equal(f.loader.hidden, false);
    assert.match(f.status.textContent, /Loading page/);
    f.emit('pageshow');
    assert.equal(f.content.hidden, false);
    assert.equal(f.content.inert, false);
    assert.equal(f.content.attributes.has('aria-busy'), false);
    assert.equal(f.loader.hidden, true);
    assert.equal(f.browser.scrollY, 200);
    assert.equal(f.focus.calls, 1);
});

test('fast navigation clears pending skeletons before they can flash', () => {
    const f = fixture();
    f.emit('click', click('https://payroll.test/dashboard'));
    f.emit('pageshow');
    f.advance(500);
    assert.equal(f.loader.hidden, true);
    assert.equal(f.content.hidden, false);
});

test('ignores modified clicks, new tabs, downloads, prevented events and fragment links', () => {
    for (const event of [
        click('/employees', {}, { ctrlKey: true }), click('/employees', {}, { metaKey: true }),
        click('/employees', {}, { shiftKey: true }), click('/employees', {}, { altKey: true }),
        click('/employees', {}, { button: 1 }), click('/employees', {}, { defaultPrevented: true }),
        click('/employees', { target: '_blank' }), click('/employees', { download: '' }),
        click('/employees', { 'data-no-page-loading': '' }), click('#'), click('#details'),
    ]) {
        const f = fixture();
        f.emit('click', event);
        f.advance(200);
        assert.equal(f.loader.hidden, true);
    }
});

test('waits for confirmation and validation before showing a form submission skeleton', () => {
    const f = fixture();
    const form = element({ method: 'post', action: '/employees' });
    f.emit('submit', { target: form, defaultPrevented: true });
    f.advance(200);
    assert.equal(f.loader.hidden, true);
    f.emit('submit', { target: form });
    f.advance(200);
    assert.deepEqual(f.preview.children, ['form']);
});

test('respects form targets, download actions and submitter overrides', () => {
    for (const attributes of [{ target: '_blank' }, { action: '/reports/1/download' }, { method: 'dialog' }]) {
        const f = fixture();
        f.emit('submit', { target: element({ method: 'get', action: '/employees', ...attributes }) });
        f.advance(200);
        assert.equal(f.loader.hidden, true);
    }
    const f = fixture();
    f.emit('submit', { target: element({ method: 'post', action: '/employees' }),
        submitter: element({ formaction: '/dashboard', formmethod: 'get' }) });
    f.advance(200);
    assert.deepEqual(f.preview.children, ['dashboard']);
});

test('allows a slow loading preview to be dismissed without replacing form content', () => {
    const f = fixture();
    f.content.unsavedValue = 'Unsaved payroll input';
    f.emit('click', click('/employees'));
    f.advance(200);
    f.advance(15000);
    assert.match(f.status.textContent, /Still loading/);
    f.dismiss.click();
    assert.equal(f.content.hidden, false);
    assert.equal(f.content.unsavedValue, 'Unsaved payroll input');
    assert.equal(f.loader.hidden, true);
});
