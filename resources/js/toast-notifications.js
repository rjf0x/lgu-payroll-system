export function alertNotification(options = {}, doc = globalThis.document, browser = globalThis.window) {
    let timer;
    let onVisibilityChange;
    let onPageHide;
    let onPageShow;

    return {
        visible: !options.toast,
        dismissed: false,
        hovered: false,
        focused: false,

        init() {
            onVisibilityChange = () => this.schedule();
            onPageHide = () => {
                browser.clearTimeout(timer);
                if (options.toast) {
                    this.dismiss(false);
                    this.$el.style.display = 'none';
                }
            };
            onPageShow = () => this.schedule();
            doc.addEventListener('visibilitychange', onVisibilityChange);
            browser.addEventListener('pagehide', onPageHide);
            browser.addEventListener('pageshow', onPageShow);
            this.$nextTick(() => {
                if (this.dismissed) { return; }
                this.visible = true;
                this.schedule();
            });
        },

        schedule() {
            browser.clearTimeout(timer);
            if (options.autoDismiss && options.duration > 0 && this.visible
                && !this.dismissed && !this.hovered && !this.focused && !doc.hidden) {
                timer = browser.setTimeout(() => this.dismiss(false), options.duration);
            }
        },

        dismiss(restoreFocus = true) {
            if (this.dismissed) { return; }
            const hadFocus = restoreFocus && this.$el.contains(doc.activeElement);
            this.dismissed = true;
            this.visible = false;
            browser.clearTimeout(timer);
            if (hadFocus) {
                this.$nextTick(() => {
                    const nextButton = [...doc.querySelectorAll('[data-alert-dismiss]')].find(button =>
                        !this.$el.contains(button) && button.getClientRects().length > 0
                        && !button.closest('[data-alert]').inert);
                    (nextButton || doc.getElementById('main-content') || doc.querySelector('main input'))?.focus({ preventScroll: true });
                });
            }
        },

        destroy() {
            browser.clearTimeout(timer);
            doc.removeEventListener('visibilitychange', onVisibilityChange);
            browser.removeEventListener('pagehide', onPageHide);
            browser.removeEventListener('pageshow', onPageShow);
        },
    };
}
