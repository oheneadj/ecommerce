<?php

/**
 * Deletes abandoned guest carts that were never converted into an order.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * `ResolveCurrentCart` creates a fresh, empty `carts` row every time a
 * guest without a live one visits `/cart` or `/checkout` — nothing ever
 * cleaned these up, so a script hitting either route repeatedly with
 * cookies stripped between requests could grow this table without bound.
 * A cart is safe to delete once it's older than this: the guest session
 * cookie it's keyed to has long since expired (`SESSION_LIFETIME`, in
 * minutes), so it's already unreachable by anyone, and it was never
 * converted into an order (`cascadeOnDelete()` on `cart_items.cart_id`
 * handles the line items; a cart tied to a real order is never touched).
 */
class PruneStaleGuestCarts
{
    use AsAction;

    public function handle(): int
    {
        $cutoff = now()->subMinutes(max(1440, (int) config('session.lifetime') * 2));

        return Cart::query()
            ->whereNull('user_id')
            ->whereDoesntHave('order')
            ->where('updated_at', '<', $cutoff)
            ->delete();
    }
}
