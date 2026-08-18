<?php

/**
 * Shared payment-status tracking (pending/failed/retry) for order-facing Livewire pages.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront\Concerns;

use App\Actions\Payment\InitiatePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Livewire\Attributes\Computed;

/**
 * Used by both OrderConfirmationPage (guest + authenticated, the page every
 * checkout outcome now lands on) and OrderDetailPage (authenticated, the
 * richer ongoing-tracking view) — both need to know whether a payment is
 * still being confirmed, whether the latest attempt failed and can be
 * retried, and how to actually retry it, so that logic lives here once
 * rather than drifting apart across two implementations.
 *
 * @property Order $order the host component's own order property
 */
trait TracksPaymentStatus
{
    use RespondsToPaymentInitiation;

    /**
     * A payment can settle asynchronously — the webhook (or the
     * VerifyPendingPayments polling fallback) confirms it well after this
     * page has already rendered, so the customer otherwise has to manually
     * reload to see it move past "Pending". Only polled while there's
     * actually something to wait for (hasPendingPayment), not indefinitely
     * on an already-resolved order.
     */
    public function refreshOrder(): void
    {
        $this->order->refresh()->load(['payments', 'statusHistories', 'shipment']);
    }

    /**
     * Gates `wire:poll` in the view — no reason to keep polling once every
     * payment attempt has already settled one way or the other.
     */
    #[Computed]
    public function hasPendingPayment(): bool
    {
        return $this->order->payments->contains(fn (Payment $payment): bool => $payment->status === PaymentStatus::Pending);
    }

    /**
     * Only offered when the *most recent* payment attempt is the failed
     * one — not merely "a failed payment exists somewhere in this order's
     * history". A retry that later succeeds (e.g. the webhook settling a
     * second attempt after the first failed) leaves that original Failed
     * row in place as an accurate record of what happened, but it must
     * stop being retryable the moment a later attempt supersedes it —
     * otherwise a fully paid order still shows a "Retry payment" button.
     * A Pending latest attempt is likewise never retryable here — it's
     * already being chased by the webhook/polling fallback, and
     * InitiatePayment's own idempotency would just hand back that same
     * still-pending attempt anyway rather than starting a fresh one.
     */
    #[Computed]
    public function latestFailedPayment(): ?Payment
    {
        $latestPayment = $this->order->payments->sortByDesc('id')->first();

        return $latestPayment?->status === PaymentStatus::Failed ? $latestPayment : null;
    }

    /**
     * Starts a brand-new payment attempt against this same order/provider
     * — never a new order, never a new order number. Re-validates the
     * order isn't already paid (a stale page left open across two tabs
     * must not double-charge) and that there's actually a failed payment
     * to retry. Works identically for a guest as for an authenticated
     * customer — reaching this page at all already required knowing the
     * order's own unguessable URL, and retrying doesn't touch anything
     * more sensitive than initiating another payment attempt.
     */
    public function retryPayment(): void
    {
        $this->order->refresh();

        $failedPayment = $this->latestFailedPayment;

        if ($this->order->status === OrderStatus::Paid || $failedPayment === null) {
            return;
        }

        $payment = InitiatePayment::run($this->order, $failedPayment->provider);

        if ($payment->status === PaymentStatus::Failed) {
            $this->addError('retryPayment', $payment->metadata['error'] ?? 'Payment could not be started. Please try again.');

            return;
        }

        $this->respondToPaymentInitiation($payment, $this->order);
    }
}
