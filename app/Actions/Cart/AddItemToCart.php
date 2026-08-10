<?php

/**
 * Adds a variant/quantity to a cart, or increases quantity if already present.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Exceptions\CartQuantityExceedsStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Never holds/reserves stock — the cart is a wishlist-like intent, not a
 * hold on inventory (BRD FR-3.1). Availability is only actually reserved
 * once CreateOrderFromCart runs at checkout. It does, however, cap the
 * quantity a customer can queue up against the variant's current cached
 * `stock` — a customer can never even add more than physically exists,
 * even though that alone doesn't guarantee it'll still be there at
 * checkout (two shoppers can still race for the last unit; that race is
 * resolved by ReserveStockForOrder, not here).
 *
 * The cart row is locked for the duration of the transaction — otherwise
 * the existing-item check is a check-then-write race: two simultaneous
 * "add to cart" requests for the same variant could both read no existing
 * row and both try to insert one, with the second hitting `cart_items`'
 * `(cart_id, product_variant_id)` unique constraint instead of correctly
 * incrementing the first's row. Same pattern as CreateOrderFromCart's cart
 * lock and MergeGuestCartIntoUser.
 *
 * @throws CartQuantityExceedsStockException when the resulting quantity
 *                                           (existing cart quantity + this call's) would exceed the variant's stock
 */
class AddItemToCart
{
    use AsAction;

    /**
     * Adds `$quantity` of `$variant` to `$cart`, capped at available stock.
     */
    public function handle(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $variant, $quantity): CartItem {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $item = $cart->items()->where('product_variant_id', $variant->id)->first();

            $resultingQuantity = ($item !== null ? $item->quantity : 0) + $quantity;

            if ($resultingQuantity > $variant->stock) {
                throw new CartQuantityExceedsStockException($variant->stock);
            }

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
