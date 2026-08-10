<?php

/**
 * Sets a cart line item's quantity to an exact value (not a delta).
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Exceptions\CartQuantityExceedsStockException;
use App\Models\Cart;
use App\Models\ProductVariant;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Takes the owning Cart and scopes the update through it, same ownership
 * pattern as RemoveItemFromCart/AddItemToCart — a bare CartItem parameter
 * would have no way to verify the item actually belongs to the acting
 * cart. A quantity of zero or less removes the line entirely, matching
 * what a "0" in a quantity stepper means to a customer.
 *
 * @throws CartQuantityExceedsStockException when $quantity exceeds the
 *                                           variant's stock — same cap AddItemToCart enforces, for the same reason.
 */
class UpdateCartItemQuantity
{
    use AsAction;

    /**
     * Sets the line's quantity, or removes it entirely if `$quantity <= 0`.
     */
    public function handle(Cart $cart, ProductVariant $variant, int $quantity): void
    {
        if ($quantity <= 0) {
            RemoveItemFromCart::run($cart, $variant);

            return;
        }

        if ($quantity > $variant->stock) {
            throw new CartQuantityExceedsStockException($variant->stock);
        }

        $cart->items()->where('product_variant_id', $variant->id)->update(['quantity' => $quantity]);
    }
}
