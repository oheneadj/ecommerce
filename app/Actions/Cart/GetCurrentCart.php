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
 * is then "closed" — once that's happened, the user needs a fresh cart
 * for their next purchase rather than reusing the one that already
 * checked out. Shared by the Cart page and (eventually) Checkout, so
 * both always agree on which cart is "the" cart for this user right now.
 */
class GetCurrentCart
{
    use AsAction;

    public function handle(User $user): Cart
    {
        return Cart::query()
            ->where('user_id', $user->id)
            ->whereDoesntHave('order')
            ->latest('id')
            ->first()
            ?? Cart::query()->create(['user_id' => $user->id]);
    }
}
