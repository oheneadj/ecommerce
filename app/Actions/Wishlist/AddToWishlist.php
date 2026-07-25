<?php

/**
 * Saves a variant to a customer's wishlist.
 */

declare(strict_types=1);

namespace App\Actions\Wishlist;

use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WishlistItem;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Registered users only — no guest wishlist (BRD FR-8.1). Adding an
 * already-wishlisted variant again is a no-op, not a duplicate row —
 * `(user_id, product_variant_id)` is unique.
 */
class AddToWishlist
{
    use AsAction;

    public function handle(User $user, ProductVariant $variant): WishlistItem
    {
        return WishlistItem::query()->firstOrCreate([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);
    }
}
