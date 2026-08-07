<?php

/**
 * Adds a variant/quantity to a cart, or increases quantity if already present.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Never touches stock or creates a reservation — the cart is a wishlist-like
 * intent, not a hold on inventory (BRD FR-3.1). Availability is only
 * actually checked and reserved once CreateOrderFromCart runs at checkout.
 *
 * The cart row is locked for the duration of the transaction — otherwise
 * the existing-item check is a check-then-write race: two simultaneous
 * "add to cart" requests for the same variant could both read no existing
 * row and both try to insert one, with the second hitting `cart_items`'
 * `(cart_id, product_variant_id)` unique constraint instead of correctly
 * incrementing the first's row. Same pattern as CreateOrderFromCart's cart
 * lock and MergeGuestCartIntoUser.
 */
class AddItemToCart
{
    use AsAction;

    public function handle(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $variant, $quantity): CartItem {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $item = $cart->items()->where('product_variant_id', $variant->id)->first();

            if ($item !== null) {
                $item->increment('quantity', $quantity);

                return $item;
            }

            return $cart->items()->create([
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
            ]);
        });
    }
}
