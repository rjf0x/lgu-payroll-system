const activity = document.getElementById('header-activity');

if (activity) {
    const trigger = activity.querySelector('summary');

    document.addEventListener('click', (event) => {
        if (activity.open && !activity.contains(event.target)) {
            activity.open = false;
        }
    });
    activity.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activity.open) {
            event.preventDefault();
            activity.open = false;
            trigger.focus();
        }
    });
    activity.addEventListener('focusout', () => {
        requestAnimationFrame(() => {
            if (!activity.contains(document.activeElement)) { activity.open = false; }
        });
    });
    activity.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => { activity.open = false; });
    });
}
const besuStatus = document.getElementById('besu-status');

if (besuStatus) {
    const label = besuStatus.querySelector('[data-besu-label]');
    const dot = besuStatus.querySelector('[data-besu-dot]');
    let checking = false;

    const checkBesuStatus = async () => {
        if (checking || document.hidden) { return; }
        checking = true;

        try {
            const response = await fetch(besuStatus.dataset.statusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: AbortSignal.timeout(8000),
            });
            if (response.status !== 200 && response.status !== 503) {
                throw new Error('Status unavailable');
            }
            const status = await response.json();
            if (status.scope !== 'node_connectivity' || typeof status.connected !== 'boolean'
                || status.connected !== (response.status === 200)) {
                throw new Error('Invalid connection status');
            }
            label.textContent = status.connected ? 'Node connected' : 'Node unavailable';
            dot.classList.remove('bg-slate-400', 'bg-emerald-600', 'bg-red-600');
            dot.classList.add(status.connected ? 'bg-emerald-600' : 'bg-red-600');
        } catch {
            label.textContent = 'Node status unavailable';
            dot.classList.remove('bg-emerald-600', 'bg-red-600');
            dot.classList.add('bg-slate-400');
        } finally {
            checking = false;
        }
    };

    label.textContent = 'Checking node…';
    checkBesuStatus();
    window.setInterval(checkBesuStatus, 30000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { checkBesuStatus(); }
    });
}
