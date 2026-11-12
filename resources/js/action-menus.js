let activeMenu = null;

document.querySelectorAll('[data-action-menu]').forEach((container) => {
    const trigger = container.querySelector('[data-action-menu-trigger]');
    const panel = container.querySelector('[data-action-menu-panel]');

    if (typeof panel.showPopover !== 'function') {
        trigger.hidden = true;
        panel.removeAttribute('popover');
        panel.classList.remove('ui-action-menu-panel');
        return;
    }

    const actions = () => [...panel.querySelectorAll('a[href], button:not(:disabled)')]
        .filter((action) => action.getAttribute('aria-disabled') !== 'true');

    const positionPanel = () => {
        const margin = 8;
        const gap = 4;
        const bounds = trigger.getBoundingClientRect();
        panel.style.maxHeight = `${window.innerHeight - margin * 2}px`;
        const menu = panel.getBoundingClientRect();
        const below = window.innerHeight - bounds.bottom - margin;
        const above = bounds.top - margin;
        const openBelow = below >= menu.height + gap || below >= above;
        const available = Math.max(0, (openBelow ? below : above) - gap);
        panel.style.maxHeight = `${available}px`;
        panel.style.left = `${Math.max(margin, Math.min(bounds.right - menu.width, window.innerWidth - menu.width - margin))}px`;
        panel.style.top = `${openBelow ? bounds.bottom + gap : Math.max(margin, bounds.top - Math.min(menu.height, available) - gap)}px`;
    };

    panel.addEventListener('toggle', (event) => {
        if (event.newState === 'open') {
            activeMenu = { panel, positionPanel };
            positionPanel();
        } else if (activeMenu?.panel === panel) {
            activeMenu = null;
        }
    });

    trigger.addEventListener('keydown', (event) => {
        if (!['ArrowDown', 'ArrowUp'].includes(event.key)) { return; }
        event.preventDefault();
        if (!panel.matches(':popover-open')) { panel.showPopover(); }
        positionPanel();
        const items = actions();
        (event.key === 'ArrowUp' ? items.at(-1) : items[0])?.focus();
    });

    panel.addEventListener('keydown', (event) => {
        if (!['ArrowDown', 'ArrowUp', 'Home', 'End', 'Escape'].includes(event.key)) { return; }
        event.preventDefault();
        if (event.key === 'Escape') {
            panel.hidePopover();
            trigger.focus();
            return;
        }
        const items = actions();
        const current = items.indexOf(document.activeElement);
        const index = event.key === 'Home' ? 0
            : event.key === 'End' ? items.length - 1
                : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items[index]?.focus();
    });

    panel.addEventListener('click', (event) => {
        const action = event.target.closest('a[href], button:not(:disabled)');
        if (!action || action.getAttribute('aria-disabled') === 'true') { return; }
        trigger.focus({ preventScroll: true });
        panel.hidePopover();
    });

    container.addEventListener('focusout', () => {
        requestAnimationFrame(() => {
            if (panel.matches(':popover-open') && !container.contains(document.activeElement)) {
                panel.hidePopover();
            }
        });
    });
});

window.addEventListener('resize', () => activeMenu?.positionPanel());
window.addEventListener('scroll', (event) => {
    if (activeMenu && activeMenu.panel.matches(':popover-open') && !activeMenu.panel.contains(event.target)) {
        activeMenu.panel.hidePopover();
    }
}, true);
