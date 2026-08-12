/**
 * Shared Alpine behaviors used by multiple Blade views across the app.
 *
 * Per CLAUDE.md's "Separation of Design from Logic" rule: any Alpine
 * behavior with real logic (timers, clipboard access, etc.) lives here,
 * named and reusable, instead of being duplicated inline in each view —
 * so a bug fix here applies everywhere it's used, and every view stays
 * free to redesign its markup without risking that behavior.
 */

document.addEventListener('alpine:init', () => {
    /**
     * A panel (dropdown, popover) that becomes visible when a given
     * window event fires, then auto-hides itself after `delayMs` unless
     * dismissed sooner via `close()`. `toggle()` is for a trigger button
     * that also opens/closes it manually.
     */
    window.Alpine.data('autoDismissPanel', (eventName, delayMs = 4000) => ({
        visible: false,
        autoCloseTimer: null,

        init() {
            window.addEventListener(eventName, () => {
                this.visible = true;
                clearTimeout(this.autoCloseTimer);
                this.autoCloseTimer = setTimeout(() => {
                    this.visible = false;
                }, delayMs);
            });
        },

        toggle() {
            this.visible = ! this.visible;
        },

        close() {
            this.visible = false;
        },
    }));

    /**
     * Copies `text` to the clipboard on `copy()`, briefly flipping
     * `copied` to true for a checkmark-style confirmation. Falls back to
     * a hidden-textarea + execCommand copy when `navigator.clipboard`
     * isn't available (any non-secure/non-HTTPS context), and dispatches
     * a `toast` event with `errorMessage` if copying fails outright,
     * rather than failing silently.
     */
    window.Alpine.data('copyToClipboard', (text, errorMessage) => ({
        copied: false,

        async copy() {
            try {
                if (navigator.clipboard) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const input = document.createElement('textarea');
                    input.value = text;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                }

                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 1500);
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { variant: 'error', message: errorMessage } }));
            }
        },
    }));
});
