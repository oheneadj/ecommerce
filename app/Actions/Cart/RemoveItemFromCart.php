<?php

/**
 * Removes a line item from a cart.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\ProductVariant;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Takes the owning Cart (never a bare CartItem) and scopes the delete
 * through it — same pattern as RemoveFromWishlist and its own sibling
 * AddItemToCart. A future controller/route built on top of a bare
 * `CartItem $item` parameter would have no way to verify the item
 * actually belongs to the acting user/session; scoping through the
 * relationship here makes that mistake impossible to make by accident.
 */
class RemoveItemFromCart
{
    use AsAction;

    public function handle(Cart $cart, ProductVariant $variant): void
    {
        $cart->items()->where('product_variant_id', $variant->id)->delete();
    }
}
