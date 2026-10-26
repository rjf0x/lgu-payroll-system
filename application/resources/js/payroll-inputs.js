document.querySelectorAll('[data-payroll-inputs]').forEach((form) => {
    form.querySelectorAll('[data-input-group]').forEach((group) => {
        const rows = group.querySelector('[data-input-rows]');
        const template = group.querySelector('[data-input-template]');
        let nextIndex = rows.children.length;
        const addButton = group.querySelector('[data-add-input]');
        const status = group.querySelector('[data-input-count]');
        const groupLabel = group.querySelector('h3')?.textContent?.trim() ?? 'Inputs';
        const updateGroup = () => {
            addButton.disabled = rows.children.length >= 30;
            if (status) status.textContent = `${groupLabel}: ${rows.children.length} of 30 lines entered${addButton.disabled ? '. Maximum reached.' : '.'}`;
        };
        updateGroup();

        addButton.addEventListener('click', () => {
            if (rows.children.length >= 30) return;
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace('__INDEX__', String(nextIndex));
            });
            nextIndex += 1;
            rows.append(fragment);
            rows.lastElementChild.querySelector('input')?.focus();
            updateGroup();
        });

        rows.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-input]');
            if (button) {
                const row = button.closest('[data-input-row]');
                const next = row.nextElementSibling?.querySelector('input') ?? row.previousElementSibling?.querySelector('input');
                row.remove();
                updateGroup();
                (next ?? addButton).focus();
            }
        });
    });
});
