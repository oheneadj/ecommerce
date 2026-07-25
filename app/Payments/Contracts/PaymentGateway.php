<?php

/**
 * Contract every payment provider driver must implement.
 */

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentInitiationResult;
use App\Payments\PaymentVerificationResult;
use App\Payments\RefundResult;
use Illuminate\Http\Request;

/**
 * Abstracts outbound payment provider calls so Actions never call Moolre or
 * Paystack directly — adding or swapping a provider is a new driver class +
 * config entry only. No implementation may accept or log raw card data;
 * card/payment details are handled entirely by the provider (hosted
 * checkout / redirect), never by this platform.
 */
interface PaymentGateway
{
    /**
     * Start a payment for the given order over the given channel
     * (e.g. "mobile_money", "card"), returning a redirect URL or provider
     * reference for the customer to complete payment.
     */
    public function initiate(Order $order, string $channel): PaymentInitiationResult;

    /**
     * Check a payment's current status directly with the provider — used
     * by the VerifyPendingPayments polling fallback and by server-side
     * verification of a client-reported success.
     */
    public function verify(string $providerReference): PaymentVerificationResult;

    /**
     * Request a full or partial refund of a completed payment.
     */
    public function refund(Payment $payment, int $amount, ?string $reason = null): RefundResult;

    /**
     * Verify that an inbound webhook request genuinely came from this
     * provider, using its documented signature scheme. Must be checked
     * before any business logic runs on the payload.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * A stable, unique identifier for this provider's webhook payload
     * (used as `webhook_events.event_id` for idempotency).
     */
    public function webhookEventId(Request $request): string;

    /**
     * Extract the payment's provider reference from a webhook payload, so
     * the matching Payment row can be found. The webhook's own reported
     * status is never trusted directly — HandlePaymentWebhook always
     * re-verifies via `verify()` using this reference.
     */
    public function paymentReferenceFromWebhook(Request $request): ?string;
}
