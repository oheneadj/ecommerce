<?php

/**
 * Adds a variant/quantity to a cart, or increases quantity if already present.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Never touches stock or creates a reservation — the cart is a wishlist-like
 * intent, not a hold on inventory (BRD FR-3.1). Availability is only
 * actually checked and reserved once CreateOrderFromCart runs at checkout.
 */
class AddItemToCart
{
    use AsAction;

    public function handle(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        $item = $cart->items()->where('product_variant_id', $variant->id)->first();

        if ($item !== null) {
            $item->increment('quantity', $quantity);

            return $item;
        }

        return $cart->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);
    }
}
