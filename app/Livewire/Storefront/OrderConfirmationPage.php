<?php

/**
 * Shown right after a customer places an order — the one page every checkout
 * outcome (success, still-pending, or a failed payment attempt) lands on,
 * for guest and authenticated customers alike.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Enums\PaymentStatus;
use App\Jobs\VerifyPaymentWithGateway;
use App\Livewire\Storefront\Concerns\TracksPaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Payment|null $latestFailedPayment
 * @property-read bool $hasPendingPayment
 */
#[Title('Order confirmed')]
class OrderConfirmationPage extends Component
{
    use TracksPaymentStatus;

    public Order $order;

    public function mount(string $orderUlid): void
    {
        $this->order = Order::query()
            ->with(['payments', 'statusHistories', 'shipment'])
            ->where('ulid', $orderUlid)
            ->when(
                Auth::check(),
                fn ($query) => $query->where('user_id', Auth::id()),
                fn ($query) => $query->whereNull('user_id'),
            )
            ->firstOrFail();

        $this->dispatchImmediateVerificationIfPending();
    }

    /**
     * Paystack's redirect_url/callback_url convention appends `?reference=`
     * to this page's URL — present only for a redirect-mode Paystack
     * payment, absent for popup mode (the popup itself already redirects
     * once it closes, without a reference param) and for Moolre (no
     * browser redirect at all). Only dispatched when the payment is still
     * Pending, so a customer who reloads this page after confirmation has
     * already landed doesn't trigger a pointless repeat gateway call.
     */
    private function dispatchImmediateVerificationIfPending(): void
    {
        if (! request()->has('reference')) {
            return;
        }

        $payment = $this->order->payments->sortByDesc('id')->first();

        if ($payment?->status === PaymentStatus::Pending) {
            VerifyPaymentWithGateway::dispatch($payment->id);
        }
    }

    public function render(): View
    {
        return view('livewire.storefront.order-confirmation-page');
    }
}
