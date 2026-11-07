const navigation = document.getElementById('app-navigation');

if (navigation) {
    const desktop = window.matchMedia('(min-width: 1024px)');
    const updateNavigation = () => { navigation.open = desktop.matches; };
    updateNavigation();
    desktop.addEventListener('change', updateNavigation);
    navigation.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !desktop.matches && navigation.open) {
            navigation.open = false;
            navigation.querySelector('summary').focus();
        }
    });
}

const errorSummary = document.querySelector('[data-error-summary]');

if (errorSummary) {
    const fields = [...document.querySelectorAll('input, select, textarea')];
    errorSummary.querySelectorAll('[data-error-field]').forEach((link) => {
        const key = link.dataset.errorField;
        const bracketName = key.replace(/\.([^.]*)/g, '[$1]');
        const field = fields.find((candidate) => [key, bracketName, `${key}[]`].includes(candidate.name));
        if (!field) { return; }
        if (!field.id) { field.id = key.replaceAll('.', '-'); }
        link.href = `#${field.id}`;
        link.addEventListener('click', (event) => {
            event.preventDefault();
            field.focus();
            field.scrollIntoView({ block: 'center' });
        });
    });
    errorSummary.focus();
}

const resetSubmission = () => {
    document.querySelectorAll('form[data-submit-feedback]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-was-submitting]').forEach((button) => {
            button.disabled = false;
            button.textContent = button.dataset.originalLabel;
            delete button.dataset.wasSubmitting;
            delete button.dataset.originalLabel;
        });
    });
    const status = document.getElementById('submission-status');
    if (status) { status.textContent = ''; }
};

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('form[data-submit-feedback]') || event.defaultPrevented) { return; }
    if (form.dataset.submitting) {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    const status = document.getElementById('submission-status');
    if (status) { status.textContent = 'Submitting. Please wait.'; }

    window.setTimeout(() => {
        if (!form.dataset.submitting) { return; }
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
            if (button.disabled) { return; }
            button.dataset.wasSubmitting = 'true';
            button.dataset.originalLabel = button.textContent;
            button.textContent = button.dataset.submittingLabel || 'Submitting…';
            button.disabled = true;
        });
    }, 0);
});

window.addEventListener('pageshow', resetSubmission);
