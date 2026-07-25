<?php

/**
 * Removes a variant from a customer's wishlist.
 */

declare(strict_types=1);

namespace App\Actions\Wishlist;

use App\Models\ProductVariant;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveFromWishlist
{
    use AsAction;

    public function handle(User $user, ProductVariant $variant): void
    {
        $user->wishlistItems()->where('product_variant_id', $variant->id)->delete();
    }
}
