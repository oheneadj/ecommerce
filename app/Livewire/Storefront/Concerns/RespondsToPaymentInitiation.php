<?php

/**
 * Shared response handling for whatever InitiatePayment produced — used by
 * both CheckoutPage (a brand-new order) and OrderDetailPage (retrying an
 * existing one), so the popup-vs-redirect-vs-confirmation branching only
 * lives in one place.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront\Concerns;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProviderSetting;

trait RespondsToPaymentInitiation
{
    /**
     * Dispatches the Paystack popup event, does a full-page redirect to
     * the provider's hosted checkout, or sends the customer straight to
     * the order confirmation page — whichever the payment result calls
     * for. Never itself decides whether the payment succeeded; that's
     * always left to the webhook/polling-verified status.
     */
    private function respondToPaymentInitiation(Payment $payment, Order $order): void
    {
        $providerSetting = PaymentProviderSetting::query()->where('provider', $payment->provider)->first();
        $accessCode = $payment->metadata['access_code'] ?? null;

        // Popup checkout never leaves this page — the JS side (see
        // resources/js/paystack-popup.js) opens Paystack's popup and
        // navigates to the confirmation page itself once it closes,
        // regardless of what the popup itself reports, since only the
        // webhook + VerifyPendingPayments polling fallback are trusted to
        // confirm payment actually succeeded.
        if ($providerSetting?->usesPaystackPopup() && $accessCode !== null) {
            $this->dispatch(
                'paystack-popup-ready',
                accessCode: $accessCode,
                publicKey: config('payments.providers.paystack.public_key'),
                confirmationUrl: route('orders.confirmation', ['order' => $order]),
            );

            return;
        }

        $redirectUrl = $payment->metadata['redirect_url'] ?? null;

        if ($redirectUrl) {
            $this->redirect($redirectUrl, navigate: false);

            return;
        }

        $this->redirectRoute('orders.confirmation', ['order' => $order], navigate: true);
    }
}
