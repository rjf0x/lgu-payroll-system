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
