<?php

/**
 * Finds the current visitor's most recent order still waiting on a successful payment.
 */

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * `Cart::scopeOpen()` closes a cart the moment its order has any Pending or
 * Success payment — by design, so retrying checkout can't overlap with a
 * payment that might still come through. The customer-facing side effect
 * is that `ResolveCurrentCart`/`GetCurrentCart` then hand back a brand-new,
 * empty cart instead: correct for stock/payment integrity, but confusing
 * on its own — a customer who navigates back to their cart or checkout
 * mid-flight (before a webhook/poll resolves the payment, or after
 * bouncing off a failed attempt) lands on what looks like an emptied cart
 * with no explanation.
 *
 * This is the other half of that fix: before showing an empty cart, check
 * whether that's *why* it's empty, and if so, send the customer to that
 * order's own status page instead — which now shows real-time pending/
 * failed/success state (see App\Livewire\Storefront\Concerns\
 * TracksPaymentStatus) rather than leaving them to wonder what happened.
 * "Most recent" only — older unresolved orders remain visible via order
 * history for an authenticated customer, this is just a courtesy for the
 * thing they were most likely just doing.
 */
class FindRecentUnresolvedOrder
{
    use AsAction;

    /**
     * Looks up by user (authenticated) or session ID (guest), matching
     * exactly how ResolveCurrentCart/GetCurrentCart identify "the current
     * visitor's cart" — so this always agrees with whichever cart those
     * would otherwise (unhelpfully) replace with a fresh empty one.
     */
    public function handle(?User $user, string $guestSessionId): ?Order
    {
        $cart = Cart::query()
            ->when(
                $user !== null,
                fn ($query) => $query->where('user_id', $user->id),
                fn ($query) => $query->where('session_id', $guestSessionId)->whereNull('user_id'),
            )
            ->whereHas('order', function ($query): void {
                $query->whereDoesntHave('payments', fn ($query) => $query->where('status', PaymentStatus::Success));
            })
            ->with('order')
            ->latest('id')
            ->first();

        return $cart?->order;
    }
}
