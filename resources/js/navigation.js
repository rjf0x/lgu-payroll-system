const navigation = document.getElementById('app-navigation');

if (navigation) {
    const desktop = window.matchMedia('(min-width: 1024px)');
    const panel = document.getElementById('navigation-panel');
    const workspace = document.getElementById('app-workspace');
    const opener = document.getElementById('navigation-open');
    const closer = document.getElementById('navigation-close');
    const backdrop = document.getElementById('navigation-backdrop');
    const search = document.getElementById('navigation-search');
    const clear = document.getElementById('navigation-search-clear');
    const results = document.getElementById('navigation-search-status');
    const empty = document.getElementById('navigation-no-results');
    const linksContainer = document.getElementById('navigation-links');
    const links = [...linksContainer.querySelectorAll('[data-navigation-link]')];
    const groups = [...linksContainer.querySelectorAll('[data-navigation-group]')];
    const sections = [...linksContainer.querySelectorAll('[data-navigation-section]')];
    const skipLink = document.querySelector('a[href="#main-content"]');
    const storageKey = `lgu-navigation-v1:${navigation.dataset.navigationUser}`;
    let previousBodyOverflow = null;
    let stored = {};

    try {
        const value = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
        if (value && typeof value === 'object' && !Array.isArray(value)) { stored = value; }
    } catch {
        stored = {};
    }

    const preferences = Object.fromEntries(groups.map((group) => {
        const id = group.dataset.navigationGroup;
        return [id, typeof stored[id] === 'boolean' ? stored[id] : group.open];
    }));
    const preferredOpen = (group) => group.dataset.currentGroup === 'true' || preferences[group.dataset.navigationGroup];

    groups.forEach((group) => {
        group.open = preferredOpen(group);
        group.querySelector('summary').addEventListener('click', () => {
            if (search.value.trim()) { return; }
            preferences[group.dataset.navigationGroup] = !group.open;
            try {
                sessionStorage.setItem(storageKey, JSON.stringify(preferences));
            } catch {
                // Disclosure controls still work when browser storage is unavailable.
            }
        });
    });

    const revealCurrentLink = () => {
        const current = links.find((link) => link.hasAttribute('aria-current') && link.getClientRects().length);
        if (!current) { return; }
        const item = current.getBoundingClientRect();
        const region = linksContainer.getBoundingClientRect();
        if (item.top < region.top) { linksContainer.scrollTop += item.top - region.top; }
        if (item.bottom > region.bottom) { linksContainer.scrollTop += item.bottom - region.bottom; }
    };

    const syncDrawer = () => {
        const modal = !desktop.matches && navigation.open;
        opener.setAttribute('aria-expanded', String(modal));
        backdrop.hidden = !modal;
        workspace.inert = modal;
        if (skipLink) { skipLink.inert = modal; }
        if (modal) {
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            panel.setAttribute('aria-label', 'Navigation');
            if (previousBodyOverflow === null) { previousBodyOverflow = document.body.style.overflow; }
            document.body.style.overflow = 'hidden';
        } else {
            panel.removeAttribute('role');
            panel.removeAttribute('aria-modal');
            panel.removeAttribute('aria-label');
            if (previousBodyOverflow !== null) {
                document.body.style.overflow = previousBodyOverflow;
                previousBodyOverflow = null;
            }
        }
    };

    const closeNavigation = (restoreFocus = true) => {
        navigation.open = desktop.matches;
        syncDrawer();
        if (restoreFocus && !desktop.matches) { opener.focus(); }
    };
    const openNavigation = (focusSearch = false) => {
        navigation.open = true;
        syncDrawer();
        if (focusSearch) { search.focus(); }
        else if (!desktop.matches) { closer.focus(); }
        requestAnimationFrame(revealCurrentLink);
    };
    const updateBreakpoint = () => {
        const active = document.activeElement;
        navigation.open = desktop.matches;
        syncDrawer();
        if (!desktop.matches && navigation.contains(active)) { opener.focus(); }
        if (desktop.matches && [opener, closer].includes(active)) { search.focus(); }
        requestAnimationFrame(revealCurrentLink);
    };

    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
    const filterNavigation = () => {
        const query = normalize(search.value);
        const terms = query.split(' ').filter(Boolean);
        let count = 0;
        links.forEach((link) => {
            const group = link.closest('[data-navigation-group]');
            const text = normalize(`${group?.querySelector('summary').textContent || ''} ${link.textContent}`);
            const matches = terms.every((term) => text.includes(term));
            link.hidden = !matches;
            if (matches) { count++; }
        });
        groups.forEach((group) => {
            const matches = [...group.querySelectorAll('[data-navigation-link]')].some((link) => !link.hidden);
            group.hidden = !matches;
            group.open = query ? matches : preferredOpen(group);
        });
        sections.forEach((section) => {
            section.hidden = ![...section.querySelectorAll('[data-navigation-link]')].some((link) => !link.hidden);
        });
        clear.hidden = !query;
        empty.hidden = count > 0;
        results.textContent = query ? `${count} navigation ${count === 1 ? 'link' : 'links'} found.` : '';
        linksContainer.scrollTop = 0;
        if (!query) { requestAnimationFrame(revealCurrentLink); }
    };
    const clearSearch = () => {
        search.value = '';
        filterNavigation();
        search.focus();
    };

    navigation.dataset.navigationEnhanced = 'true';
    opener.hidden = false;
    closer.hidden = false;
    document.getElementById('navigation-search-container').hidden = false;
    opener.addEventListener('click', () => openNavigation());
    closer.addEventListener('click', () => closeNavigation());
    backdrop.addEventListener('click', () => closeNavigation());
    search.addEventListener('input', filterNavigation);
    search.addEventListener('search', filterNavigation);
    clear.addEventListener('click', clearSearch);
    desktop.addEventListener('change', updateBreakpoint);

    navigation.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            if (search.value.trim()) { clearSearch(); }
            else if (!desktop.matches) { closeNavigation(); }
        }
        if (event.key !== 'Tab' || desktop.matches || !navigation.open) { return; }
        const focusable = [...panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), summary')]
            .filter((element) => element.getClientRects().length > 0 && !element.hidden);
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
            event.preventDefault();
            first?.focus();
        }
    });
    linksContainer.addEventListener('click', (event) => {
        if (!desktop.matches && event.target.closest('[data-navigation-link]') && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
            closeNavigation(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || event.isComposing || document.querySelector('dialog[open]')) { return; }
        if (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="textbox"]')) { return; }
        event.preventDefault();
        openNavigation(true);
    });
    window.addEventListener('pageshow', () => {
        if (!desktop.matches) { closeNavigation(false); }
    });
    updateBreakpoint();
}
