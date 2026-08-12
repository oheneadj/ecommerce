<?php

/**
 * Customer-facing order detail/tracking — a single order's items, shipping
 * destination, payment attempts, and status history timeline.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Payment\InitiatePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Storefront\Concerns\RespondsToPaymentInitiation;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read string $addressLines
 * @property-read Payment|null $latestFailedPayment
 */
#[Title('Order detail')]
class OrderDetailPage extends Component
{
    use RespondsToPaymentInitiation;

    public Order $order;

    public function mount(string $orderUlid): void
    {
        $this->order = Auth::user()->orders()
            ->with(['items', 'payments', 'statusHistories', 'shipment'])
            ->where('ulid', $orderUlid)
            ->firstOrFail();
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
     * to retry, exactly the same "don't trust the button was clicked in
     * a still-valid state" reasoning CheckoutPage::placeOrder() already
     * applies to its own re-validation.
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

    /**
     * The order's snapshotted delivery address, flattened into a single
     * comma-separated display line — skips whichever parts weren't
     * captured (line2/region are often blank).
     */
    #[Computed]
    public function addressLines(): string
    {
        return collect([
            $this->order->address_snapshot['line1'] ?? null,
            $this->order->address_snapshot['line2'] ?? null,
            $this->order->address_snapshot['city'] ?? null,
            $this->order->address_snapshot['region'] ?? null,
        ])->filter()->implode(', ');
    }

    public function render(): View
    {
        return view('livewire.storefront.order-detail-page');
    }
}
