import './navigation.js';
import './header.js';
import './action-menus.js';
import './payroll-detail.js';
import './page-loading.js';
const confirmation = document.getElementById('confirmation-dialog');
let pendingConfirmation = null;
const submissionLabels = new WeakMap();

if (confirmation && typeof confirmation.showModal === 'function') {
    window.payrollConfirmationReady = true;
    document.getElementById('confirmation-cancel').addEventListener('click', () => confirmation.close());
    document.getElementById('confirmation-accept').addEventListener('click', () => {
        const pending = pendingConfirmation;
        confirmation.close();
        if (!pending) { return; }
        pending.form.dataset.confirmed = 'true';
        pending.form.requestSubmit(pending.submitter);
    });
    confirmation.addEventListener('close', () => { pendingConfirmation = null; });
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
            button.removeAttribute('aria-busy');
            const label = button.querySelector('[data-button-label]');
            if (label && submissionLabels.has(button)) {
                label.replaceChildren(...submissionLabels.get(button));
                submissionLabels.delete(button);
            } else if (button.dataset.originalLabel !== undefined) {
                button.textContent = button.dataset.originalLabel;
            }
            delete button.dataset.wasSubmitting;
            delete button.dataset.originalLabel;
        });
    });
    const status = document.getElementById('submission-status');
    if (status) { status.textContent = ''; }
};

document.addEventListener('input', (event) => {
    if (event.target.matches('textarea[name="remarks"]')) { event.target.setCustomValidity(''); }
});
document.addEventListener('click', (event) => {
    const button = event.target.closest('form[data-confirm] button[type="submit"]');
    button?.form.querySelector('textarea[name="remarks"]')?.setCustomValidity('');
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('form[data-submit-feedback]') || event.defaultPrevented) { return; }
    if (form.dataset.submitting) {
        event.preventDefault();
        return;
    }

    const remarks = form.querySelector('textarea[name="remarks"]');
    if (event.submitter?.hasAttribute('data-requires-remarks') && remarks && !remarks.value.trim()) {
        event.preventDefault();
        remarks.setCustomValidity('Enter a reason for returning or rejecting this payroll.');
        remarks.reportValidity();
        return;
    }

    if (form.hasAttribute('data-confirm') && window.payrollConfirmationReady && !form.dataset.confirmed) {
        event.preventDefault();
        const submitter = event.submitter;
        document.getElementById('confirmation-title').textContent = submitter?.dataset.confirmTitle || form.dataset.confirmTitle || 'Confirm payroll action';
        document.getElementById('confirmation-description').textContent = submitter?.dataset.confirmDescription || form.dataset.confirmDescription;
        document.getElementById('confirmation-context').textContent = form.dataset.confirmContext;
        pendingConfirmation = { form, submitter };
        confirmation.showModal();
        return;
    }
    delete form.dataset.confirmed;

    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    const status = document.getElementById('submission-status');
    if (status) { status.textContent = 'Submitting. Please wait.'; }

    window.setTimeout(() => {
        if (!form.dataset.submitting) { return; }
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
            if (button.disabled) { return; }
            button.dataset.wasSubmitting = 'true';
            button.setAttribute('aria-busy', 'true');
            const label = button.querySelector('[data-button-label]');
            if (label) {
                submissionLabels.set(button, [...label.childNodes]);
                label.textContent = button.dataset.submittingLabel || 'Submitting…';
            } else {
                button.dataset.originalLabel = button.textContent;
                button.textContent = button.dataset.submittingLabel || 'Submitting…';
            }
            button.disabled = true;
        });
    }, 0);
});

window.addEventListener('pageshow', resetSubmission);

const passwordToggle = document.getElementById('toggle-password');
const passwordField = document.getElementById('password');

if (passwordToggle && passwordField) {
    const setPasswordVisibility = (visible) => {
        passwordField.type = visible ? 'text' : 'password';
        passwordToggle.querySelector('[data-button-label]').textContent = visible ? 'Hide' : 'Show';
        passwordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('aria-pressed', String(visible));
    };

    passwordToggle.hidden = false;
    passwordToggle.addEventListener('click', () => setPasswordVisibility(passwordField.type === 'password'));
    window.addEventListener('pageshow', () => setPasswordVisibility(false));
}
