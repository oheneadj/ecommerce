<?php

/**
 * Shown right after a customer places an order — confirms it was received
 * and links onward to its own tracking page. Authoritative payment
 * confirmation still only ever comes from a driver's own verify() call
 * (webhook-triggered, or the polling fallback) — never from the customer
 * simply having landed here — but Paystack's redirect appends the
 * transaction reference to this page's URL specifically so a caller can
 * check sooner than the ~2-minute polling sweep, rather than always
 * waiting on it.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Enums\PaymentStatus;
use App\Jobs\VerifyPaymentWithGateway;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Order confirmed')]
class OrderConfirmationPage extends Component
{
    public Order $order;

    public function mount(string $orderUlid): void
    {
        $this->order = Order::query()
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

        $payment = $this->order->payments()->latest('id')->first();

        if ($payment?->status === PaymentStatus::Pending) {
            VerifyPaymentWithGateway::dispatch($payment->id);
        }
    }

    public function render(): View
    {
        return view('livewire.storefront.order-confirmation-page');
    }
}
