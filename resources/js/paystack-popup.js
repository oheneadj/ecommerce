/**
 * Handles the Paystack popup checkout flow — a `paystack-popup-ready`
 * browser event, dispatched from CheckoutPage::placeOrder() once a
 * Paystack transaction has been initialized server-side with popup mode
 * selected, carries the access code needed to open Paystack's Inline.js
 * popup without a full-page redirect.
 *
 * NOTE: the exact PaystackPop instantiation/resumeTransaction() call shape
 * below is based on Paystack's documented pattern (server-side initialize
 * → access_code → client-side resumeTransaction) but has not been
 * live-verified against Paystack's current Inline.js build — confirm this
 * against https://paystack.com/docs/developer-tools/inlinejs/ and a real
 * sandbox transaction before this ships to a client, per the popup-mode
 * caveat in CHANGELOG.md.
 */

const PAYSTACK_SCRIPT_URL = 'https://js.paystack.co/v2/inline.js';

function loadPaystackScript() {
    if (window.PaystackPop) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = PAYSTACK_SCRIPT_URL;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Could not load the Paystack checkout script.'));
        document.head.appendChild(script);
    });
}

window.addEventListener('paystack-popup-ready', async (event) => {
    const { accessCode, confirmationUrl } = event.detail[0] ?? event.detail;

    try {
        await loadPaystackScript();

        const popup = new window.PaystackPop();

        popup.resumeTransaction({
            accessCode,
            onSuccess: () => {
                window.location.href = confirmationUrl;
            },
            onCancel: () => {
                window.location.href = confirmationUrl;
            },
            onError: () => {
                window.location.href = confirmationUrl;
            },
        });
    } catch (e) {
        window.dispatchEvent(new CustomEvent('toast', {
            detail: { variant: 'error', message: 'Could not open the payment popup. Please try again.' },
        }));
    }
});
