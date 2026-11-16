export function pageKind(destination, dashboardUrl) {
    const base = new URL(dashboardUrl).pathname.replace(/\/dashboard\/?$/, '');
    const path = destination.pathname.slice(base.length).replace(/^\/+|\/+$/g, '');
    if (!path || /^(login|logout)$/.test(path)) { return 'auth'; }
    if (path === 'dashboard') { return 'dashboard'; }
    if (/(^|\/)(create|edit)$/.test(path) || path === 'system-settings') { return 'form'; }
    if (/\/payslips$/.test(path)) { return 'table'; }
    if (/^reports(?:\/|$)/.test(path) || /\/(summary|deductions|register)$/.test(path) || /^payslips\/[^/]+$/.test(path)) { return 'report'; }
    return /\/\d+(?:\/|$)/.test(path) ? 'detail' : 'table';
}

export function isPageDestination(destination, current) {
    return ['http:', 'https:'].includes(destination.protocol)
        && destination.origin === current.origin
        && !/(^|\/)(export|download|status)(\/|$)|\.(csv|xlsx?|pdf|zip)$/i.test(destination.pathname)
        && !(destination.hash && destination.pathname === current.pathname && destination.search === current.search);
}

export function initializePageLoading(doc, browser) {
    const loader = doc.querySelector('[data-page-loader]');
    const content = doc.querySelector('[data-page-content]');
    if (!loader || !content) { return; }
    const preview = loader.querySelector('[data-page-loading-preview]');
    const status = loader.querySelector('[data-page-loading-status]');
    let pendingTimer;
    let slowTimer;
    let previousFocus;
    let previousScroll;

    const reset = () => {
        browser.clearTimeout(pendingTimer);
        browser.clearTimeout(slowTimer);
        const wasVisible = !loader.hidden;
        loader.hidden = true;
        preview.replaceChildren();
        status.textContent = '';
        content.hidden = false;
        content.inert = false;
        content.removeAttribute('aria-busy');
        if (wasVisible) {
            previousFocus?.focus({ preventScroll: true });
            browser.scrollTo({ top: previousScroll, behavior: 'instant' });
        }
    };

    const start = (kind) => {
        browser.clearTimeout(pendingTimer);
        pendingTimer = browser.setTimeout(() => {
            const template = loader.querySelector(`[data-loading-template="${kind}"]`);
            if (!template || !loader.hidden) { return; }
            previousFocus = doc.activeElement;
            previousScroll = browser.scrollY;
            preview.replaceChildren(template.content.cloneNode(true));
            content.inert = true;
            content.hidden = true;
            content.setAttribute('aria-busy', 'true');
            loader.hidden = false;
            status.textContent = 'Loading page. Please wait.';
            browser.scrollTo({ top: 0, behavior: 'instant' });
            slowTimer = browser.setTimeout(() => {
                status.textContent = 'Still loading. You can show the current page while you wait.';
            }, 15000);
        }, 180);
    };

    browser.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
        const link = event.target.closest?.('a[href]');
        if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-page-loading') || (link.target && link.target !== '_self')) { return; }
        if (link.getAttribute('href')?.startsWith('#')) { return; }
        const destination = new URL(link.href, browser.location.href);
        if (!isPageDestination(destination, new URL(browser.location.href))) { return; }
        start(pageKind(destination, loader.dataset.dashboardUrl));
    });

    browser.addEventListener('submit', (event) => {
        const form = event.target;
        const submitter = event.submitter;
        const method = (submitter?.getAttribute('formmethod') || form.method || 'get').toLowerCase();
        const target = submitter?.getAttribute('formtarget') || form.target;
        if (event.defaultPrevented || method === 'dialog' || (target && target !== '_self') || form.hasAttribute('data-no-page-loading')) { return; }
        const destination = new URL(submitter?.getAttribute('formaction') || form.action, browser.location.href);
        if (!isPageDestination(destination, new URL(browser.location.href))) { return; }
        let kind = method === 'get' ? pageKind(destination, loader.dataset.dashboardUrl) : loader.dataset.currentKind;
        if (/\/login\/?$/.test(destination.pathname) && method === 'post') { kind = 'dashboard'; }
        if (/\/logout\/?$/.test(destination.pathname)) { kind = 'auth'; }
        start(kind);
    });

    loader.querySelector('[data-page-loading-dismiss]').addEventListener('click', reset);
    browser.addEventListener('pageshow', reset);
    browser.addEventListener('beforeprint', reset);
    browser.navigation?.addEventListener('navigateerror', reset);
}

if (typeof document !== 'undefined') {
    initializePageLoading(document, window);
}
