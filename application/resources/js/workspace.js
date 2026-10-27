const navigation = document.getElementById('primary-navigation');
document.querySelectorAll('[data-print-document]').forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', () => window.print());
});
const navigationToggle = document.querySelector('[data-navigation-toggle]');

if (navigation && navigationToggle) {
    const narrowViewport = window.matchMedia('(max-width: 900px)');
    const setNavigation = (open) => {
        document.body.dataset.navOpen = String(open);
        navigationToggle.setAttribute('aria-expanded', String(open));
    };
    setNavigation(!narrowViewport.matches);
    navigationToggle.addEventListener('click', () => {
        setNavigation(navigationToggle.getAttribute('aria-expanded') !== 'true');
    });
    narrowViewport.addEventListener('change', () => setNavigation(!narrowViewport.matches));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && narrowViewport.matches && document.body.dataset.navOpen === 'true') {
            const focusWasInside = navigation.contains(document.activeElement);
            setNavigation(false);
            if (focusWasInside) navigationToggle.focus();
        }
    });
}

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    button.hidden = false;
    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        const label = reveal ? 'Hide password' : 'Show password';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', String(reveal));
        button.querySelector('[data-password-show]').toggleAttribute('hidden', reveal);
        button.querySelector('[data-password-hide]').toggleAttribute('hidden', !reveal);
    });
});

const fields = [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')];
const fieldKey = (field) => field.name?.replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
const findField = (key) => {
    const exact = fields.filter((field) => fieldKey(field) === key);
    if (exact.length) return exact.length === 1 ? exact[0] : null;
    const parents = fields.filter((field) => key.startsWith(`${fieldKey(field)}.`));
    return parents.length === 1 ? parents[0] : null;
};

fields.forEach((field, index) => {
    if (!field.id) field.id = `form-field-${index}`;
});

document.querySelectorAll('[data-error-field]').forEach((link) => {
    const key = link.dataset.errorField;
    const field = findField(key);
    if (!field) return;
    link.href = `#${field.id}`;
    field.setAttribute('aria-invalid', 'true');
    let inline = [...document.querySelectorAll('[data-field-error]')].find((error) => error.dataset.fieldError === key);
    if (!inline) {
        inline = document.createElement('p');
        inline.className = 'field-error';
        inline.dataset.fieldError = key;
        inline.textContent = link.textContent;
        const label = field.closest('label');
        (label ?? field).insertAdjacentElement('afterend', inline);
    }
    if (!inline.id) inline.id = `${field.id}-error`;
    const descriptions = new Set((field.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean));
    descriptions.add(inline.id);
    field.setAttribute('aria-describedby', [...descriptions].join(' '));
    link.addEventListener('click', (event) => {
        event.preventDefault();
        field.focus();
        field.scrollIntoView({ block: 'center' });
    });
});

document.querySelector('[data-error-summary]')?.focus();

document.querySelectorAll('.workspace-content .overflow-x-auto').forEach((region) => {
    const caption = region.querySelector('caption');
    if (!region.querySelector('table')) return;
    region.tabIndex = 0;
    region.setAttribute('role', 'region');
    region.setAttribute('aria-label', caption?.textContent?.trim() ?? 'Scrollable records table');
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        const button = event.submitter;
        if (!button || button.name) return;
        button.dataset.readyLabel = button.innerHTML;
        button.disabled = true;
        button.textContent = form.method.toLowerCase() === 'get' ? 'Loading…' : 'Working…';
        form.setAttribute('aria-busy', 'true');
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-ready-label]').forEach((button) => {
        button.innerHTML = button.dataset.readyLabel;
        button.disabled = false;
        delete button.dataset.readyLabel;
        button.closest('form')?.removeAttribute('aria-busy');
    });
});
