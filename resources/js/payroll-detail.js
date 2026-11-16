document.querySelectorAll('[data-payroll-detail-form]').forEach((form) => {
    const tabList = form.querySelector('[data-payroll-tabs]');
    const tabs = [...form.querySelectorAll('[data-payroll-tab]')];
    const panels = [...form.querySelectorAll('[data-payroll-panel]')];
    const resetSearches = new Map();

    const selectTab = (name, focus = false) => {
        tabs.forEach((tab) => {
            const selected = tab.dataset.payrollTab === name;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) { tab.focus(); }
        });
        panels.forEach((panel) => { panel.hidden = panel.dataset.payrollPanel !== name; });
    };

    panels.forEach((panel) => {
        const name = panel.dataset.payrollPanel;
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', `${name}-tab`);
        panel.tabIndex = 0;
        panel.querySelector('[data-payroll-panel-heading]').hidden = true;

        const search = panel.querySelector('[data-payroll-search]');
        const clear = panel.querySelector('[data-payroll-search-clear]');
        const entries = [...panel.querySelectorAll('[data-payroll-entry]')];
        const additional = panel.querySelector('[data-payroll-additional]');
        let previousExpansion = null;

        const filterEntries = () => {
            const query = search.value.trim().toLocaleLowerCase();
            const terms = query.split(/\s+/).filter(Boolean);
            let matches = 0;

            entries.forEach((entry) => {
                const text = entry.dataset.searchText.toLocaleLowerCase();
                entry.hidden = !terms.every((term) => text.includes(term));
                if (!entry.hidden) { matches += 1; }
            });
            panel.querySelectorAll('[data-payroll-category], [data-payroll-entry-section]').forEach((group) => {
                group.hidden = ![...group.querySelectorAll('[data-payroll-entry]')].some((entry) => !entry.hidden);
            });
            if (additional) {
                if (query && previousExpansion === null) { previousExpansion = additional.open; }
                if (query) { additional.open = true; }
                if (!query && previousExpansion !== null) {
                    additional.open = previousExpansion;
                    previousExpansion = null;
                }
            }
            const unconfigured = panel.querySelector('[data-payroll-unconfigured]');
            if (unconfigured) { unconfigured.hidden = Boolean(query); }
            clear.hidden = !query;
            panel.querySelector('[data-payroll-no-results]').hidden = !query || matches > 0;
            panel.querySelector('[data-payroll-search-status]').textContent = query
                ? `${matches} matching ${name} fields.` : '';
        };

        search.addEventListener('input', filterEntries);
        search.addEventListener('search', filterEntries);
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); }
        });
        clear.addEventListener('click', () => {
            search.value = '';
            filterEntries();
            search.focus();
        });
        panel.querySelector('[data-payroll-search-toolbar]').hidden = false;
        resetSearches.set(panel, () => {
            search.value = '';
            filterEntries();
        });
    });

    tabs.forEach((tab, index) => {
        tab.setAttribute('role', 'tab');
        tab.addEventListener('click', () => selectTab(tab.dataset.payrollTab));
        tab.addEventListener('keydown', (event) => {
            let nextIndex;
            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            if (event.key === 'Home') { nextIndex = 0; }
            if (event.key === 'End') { nextIndex = tabs.length - 1; }
            if (nextIndex === undefined) { return; }
            event.preventDefault();
            selectTab(tabs[nextIndex].dataset.payrollTab, true);
        });
    });
    tabList.setAttribute('role', 'tablist');
    tabList.hidden = false;

    const revealField = (field) => {
        const panel = field.closest('[data-payroll-panel]');
        if (panel) {
            selectTab(panel.dataset.payrollPanel);
            resetSearches.get(panel)();
        }
        const details = field.closest('details');
        if (details) { details.open = true; }
    };

    const invalidFields = [...form.querySelectorAll('[aria-invalid="true"]')];
    selectTab('earnings');
    invalidFields.forEach((field) => {
        const details = field.closest('details');
        if (details) { details.open = true; }
    });
    const invalidAdjustment = invalidFields.find((field) => field.closest('[data-payroll-panel]'));
    if (invalidAdjustment) { revealField(invalidAdjustment); }

    let firstInvalidField = null;
    form.addEventListener('invalid', (event) => {
        if (firstInvalidField) { return; }
        firstInvalidField = event.target;
        revealField(firstInvalidField);
        window.setTimeout(() => { firstInvalidField = null; }, 0);
    }, true);

    document.querySelector('[data-error-summary]')?.addEventListener('click', (event) => {
        const link = event.target.closest('[data-error-field]');
        if (!link) { return; }
        const key = link.dataset.errorField;
        const bracketName = key.replace(/\.([^.]*)/g, '[$1]');
        const field = [...form.elements].find((candidate) => [key, bracketName].includes(candidate.name));
        if (field) { revealField(field); }
    }, true);

    form.addEventListener('input', (event) => {
        if (!event.target.name) { return; }
        const status = form.querySelector('[data-payroll-save-status]');
        const message = 'Unsaved changes. Totals update when you save.';
        if (status.textContent !== message) { status.textContent = message; }
    });
});
