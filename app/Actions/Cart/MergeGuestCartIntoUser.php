<?php

/**
 * Merges a guest session's cart into a user's cart on login.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * If the user already has a cart, the guest cart's items are folded into it
 * (quantities combined for a variant present in both) and the now-empty
 * guest cart is deleted; otherwise the guest cart is simply reassigned to
 * the user (no copy, same row). Not a documented BRD requirement — added
 * because it's the expected real-world behaviour when a shopper adds items
 * before logging in.
 *
 * Both cart rows are locked for the duration of the transaction, same
 * reason and pattern as AddItemToCart/CreateOrderFromCart's cart locks —
 * without it, a merge running concurrently with an "add to cart" request
 * on either cart is a check-then-write race on `cart_items`' unique
 * `(cart_id, product_variant_id)` constraint.
 *
 * Combined quantities are capped at the variant's stock, same invariant
 * AddItemToCart enforces on every add — without this, combining two carts
 * that were each individually within stock (one built while logged out, one
 * while authenticated) could add up to more than physically exists.
 */
class MergeGuestCartIntoUser
{
    use AsAction;

    public function handle(Cart $guestCart, User $user): Cart
    {
        return DB::transaction(function () use ($guestCart, $user): Cart {
            Cart::query()->whereKey($guestCart->id)->lockForUpdate()->first();

            $userCart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($userCart === null) {
                $guestCart->update(['user_id' => $user->id, 'session_id' => null]);

                return $guestCart;
            }

            if ($userCart->id === $guestCart->id) {
                return $userCart;
            }

            foreach ($guestCart->items as $guestItem) {
                $existing = $userCart->items()
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->first();

                $stock = $guestItem->productVariant->stock;

                if ($existing !== null) {
                    $existing->update([
                        'quantity' => min($existing->quantity + $guestItem->quantity, $stock),
                    ]);
                } else {
                    $userCart->items()->create([
                        'product_variant_id' => $guestItem->product_variant_id,
                        'quantity' => min($guestItem->quantity, $stock),
                    ]);
                }
            }

            $guestCart->delete();

            return $userCart->fresh(['items']) ?? $userCart;
        });
    }
}
