document.querySelectorAll('[data-payroll-inputs]').forEach((form) => {
    form.querySelectorAll('[data-input-group]').forEach((group) => {
        const rows = group.querySelector('[data-input-rows]');
        const template = group.querySelector('[data-input-template]');
        let nextIndex = rows.children.length;

        group.querySelector('[data-add-input]').addEventListener('click', () => {
            if (rows.children.length >= 30) return;
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace('__INDEX__', String(nextIndex));
            });
            nextIndex += 1;
            rows.append(fragment);
            rows.lastElementChild.querySelector('input')?.focus();
        });

        rows.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-input]');
            if (button) button.closest('[data-input-row]').remove();
        });
    });
});
