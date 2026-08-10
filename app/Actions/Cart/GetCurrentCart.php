<?php

/**
 * Resolves a user's current, still-open cart — creating one if none exists.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A cart converts into at most one order (`orders.cart_id` is unique) and
 * is then "closed" once that order has a payment actually in flight or
 * settled (see `Cart::scopeOpen()`) — only then does the user need a
 * fresh cart for their next purchase. A cart whose order's payment
 * attempts all failed stays open, so retrying checkout reuses the same
 * cart/order instead of orphaning it. Shared by the Cart page and
 * Checkout, so both always agree on which cart is "the" cart for this
 * user right now.
 */
class GetCurrentCart
{
    use AsAction;

    public function handle(User $user): Cart
    {
        return Cart::query()
            ->where('user_id', $user->id)
            ->open()
            ->latest('id')
            ->first()
            ?? Cart::query()->create(['user_id' => $user->id]);
    }
}
