import { test } from 'node:test';
import assert from 'node:assert/strict';
import { alertNotification } from '../../resources/js/toast-notifications.js';

function fixture(options = [{ autoDismiss: true, duration: 5000, toast: true }]) {
    const timers = new Map();
    let clock = 0;
    let timerId = 0;
    const eventTarget = (properties = {}) => ({
        ...properties,
        listeners: new Map(),
        addEventListener(name, callback) { this.listeners.set(name, [...(this.listeners.get(name) || []), callback]); },
        removeEventListener(name, callback) { this.listeners.set(name, this.listeners.get(name).filter(listener => listener !== callback)); },
        emit(name, event = {}) { this.listeners.get(name)?.forEach(callback => callback(event)); },
    });
    const main = { focus() { doc.activeElement = this; } };
    const doc = eventTarget({
        hidden: false,
        activeElement: main,
        querySelectorAll: () => alerts.map(alert => alert.button),
        getElementById: () => main,
    });
    const browser = eventTarget({
        setTimeout(callback, delay) { timers.set(++timerId, { callback, at: clock + delay }); return timerId; },
        clearTimeout(id) { timers.delete(id); },
    });
    const alerts = options.map(option => {
        const state = alertNotification(option, doc, browser);
        const root = {
            style: {},
            get inert() { return !state.visible; },
            contains: target => target === state.button,
        };
        const button = {
            focus() { doc.activeElement = this; },
            getClientRects: () => state.visible ? [{}] : [],
            closest: () => root,
        };
        Object.assign(state, { $el: root, $nextTick: callback => callback(), button });
        state.init();
        return state;
    });
    return { doc, browser, alerts, main, timers,
        advance(ms) {
            clock += ms;
            for (const [id, timer] of timers) {
                if (timer.at <= clock) { timers.delete(id); timer.callback(); }
            }
        },
    };
}

test('success closes automatically while errors wait for manual dismissal', () => {
    const f = fixture([{ autoDismiss: true, duration: 5000, toast: true }, { autoDismiss: false, duration: 5000, toast: true }]);
    assert.equal(f.doc.activeElement, f.main);
    f.advance(4999);
    assert.equal(f.alerts[0].visible, true);
    f.advance(1);
    assert.equal(f.alerts[0].visible, false);
    f.advance(60000);
    assert.equal(f.alerts[1].visible, true);
    f.alerts[1].dismiss();
    assert.equal(f.alerts[1].visible, false);
});

test('hover and keyboard focus both pause automatic dismissal until interaction ends', () => {
    const f = fixture();
    const alert = f.alerts[0];
    f.advance(4000);
    alert.hovered = true;
    alert.schedule();
    f.advance(6000);
    assert.equal(alert.visible, true);
    alert.focused = true;
    alert.hovered = false;
    alert.schedule();
    f.advance(6000);
    assert.equal(alert.visible, true);
    alert.focused = false;
    alert.schedule();
    f.advance(4999);
    assert.equal(alert.visible, true);
    f.advance(1);
    assert.equal(alert.visible, false);
});

test('manual dismissal moves focus to the next visible alert and then the page', () => {
    const f = fixture([{ autoDismiss: false }, { autoDismiss: false }]);
    f.doc.activeElement = f.alerts[0].button;
    f.alerts[0].dismiss();
    assert.equal(f.doc.activeElement, f.alerts[1].button);
    f.alerts[1].dismiss();
    assert.equal(f.doc.activeElement, f.main);
});

test('a background tab preserves feedback and leaving the page clears cached toasts', () => {
    const f = fixture([{ autoDismiss: true, duration: 5000, toast: true }, { autoDismiss: false, toast: true }]);
    f.doc.hidden = true;
    f.doc.emit('visibilitychange');
    f.advance(60000);
    assert.equal(f.alerts[0].visible, true);
    f.doc.hidden = false;
    f.doc.emit('visibilitychange');
    f.advance(5000);
    assert.equal(f.alerts[0].visible, false);
    f.browser.emit('pagehide');
    assert.equal(f.alerts[1].visible, false);
    assert.equal(f.alerts[1].$el.style.display, 'none');
    f.browser.emit('pageshow');
    assert.equal(f.alerts[1].visible, false);
    assert.equal(f.doc.activeElement, f.main);
});

test('inline notices survive browser Back and resume their timers', () => {
    const f = fixture([{ autoDismiss: true, duration: 6000, toast: false }]);
    f.browser.emit('pagehide');
    f.advance(60000);
    assert.equal(f.alerts[0].visible, true);
    f.browser.emit('pageshow');
    f.advance(5999);
    assert.equal(f.alerts[0].visible, true);
    f.advance(1);
    assert.equal(f.alerts[0].visible, false);
});

test('explicit durations are honored and zero duration disables automatic dismissal', () => {
    const f = fixture([{ autoDismiss: true, duration: 9000 }, { autoDismiss: true, duration: 0 }]);
    f.advance(8999);
    assert.equal(f.alerts[0].visible, true);
    f.advance(1);
    assert.equal(f.alerts[0].visible, false);
    assert.equal(f.alerts[1].visible, true);
});

test('destroying an Alpine component removes timers and lifecycle listeners', () => {
    const f = fixture();
    f.alerts[0].destroy();
    assert.equal(f.timers.size, 0);
    f.doc.emit('visibilitychange');
    f.browser.emit('pageshow');
    f.browser.emit('pagehide');
    assert.equal(f.timers.size, 0);
    assert.equal(f.alerts[0].visible, true);
});

test('dismissal without focus does not move the current keyboard position', () => {
    const f = fixture();
    f.alerts[0].dismiss();
    assert.equal(f.doc.activeElement, f.main);
    f.alerts[0].schedule();
    assert.equal(f.timers.size, 0);
});
