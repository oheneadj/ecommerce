<?php

/**
 * Converts a cart into a permanent Order at checkout.
 */

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Inventory\ReserveStockForOrder;
use App\Enums\OrderStatus;
use App\Exceptions\EmptyCartException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A plain `DB::transaction()` — no locking at this Action's own level; the
 * locking happens inside the nested ReserveStockForOrder/ApplyCouponToOrder
 * calls it makes (AGENTS.md Section 4a). Writes: Order + OrderItem rows
 * (with `item_snapshot`) + StockReservation + CouponUsage.
 *
 * Price is always read from the variant's *current* state at this moment,
 * never from whatever was true when the item was added to the cart — the
 * cart never locks in a price (BRD Principle 8). `item_snapshot` then
 * permanently freezes that same data on the OrderItem so a later product
 * edit/archive/delete can never change how a past order displays.
 *
 * A cart converts into at most one order — `orders.cart_id` is unique, so a
 * duplicate checkout submission (double-click, back-button resubmit)
 * returns the already-created order instead of creating a second one.
 *
 * A guest order's `user_id` is never set from a matching `guest_email` —
 * that would silently attach someone else's order to an account on a
 * coincidental email match. Linking only ever happens via ClaimGuestOrder,
 * which requires the customer to authenticate first (BRD FR-3.2a).
 *
 * External side effects (order confirmation SMS/email) are not part of
 * this Action yet — the notification system doesn't exist in this codebase
 * yet. When it's added, it must fire only `->afterCommit()`, never inside
 * this transaction.
 *
 * @throws EmptyCartException when the cart has no items
 */
class CreateOrderFromCart
{
    use AsAction;

    public function handle(
        Cart $cart,
        Address $address,
        ?string $guestEmail = null,
        ?string $guestPhone = null,
        ?string $couponCode = null,
    ): Order {
        $existing = $cart->order;

        if ($existing !== null) {
            return $existing;
        }

        $items = $cart->items()->with('productVariant.product.brand', 'productVariant.attributeValues', 'productVariant.images', 'productVariant.product.images')->get();

        if ($items->isEmpty()) {
            throw new EmptyCartException;
        }

        return DB::transaction(function () use ($cart, $address, $guestEmail, $guestPhone, $couponCode, $items): Order {
            $subtotal = $items->sum(fn ($item) => $item->productVariant->price * $item->quantity);

            $order = Order::query()->create([
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'guest_email' => $cart->user_id === null ? $guestEmail : null,
                'guest_phone' => $cart->user_id === null ? $guestPhone : null,
                'address_id' => $address->id,
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => 0,
                'shipping_total' => 0,
                'grand_total' => $subtotal,
            ]);

            foreach ($items as $item) {
                $variant = $item->productVariant;
                $product = $variant->product;

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'item_snapshot' => [
                        'product_name' => $product->name,
                        'brand_name' => $product->brand?->name,
                        'sku' => $variant->sku,
                        'attributes' => $variant->attributeValues->mapWithKeys(
                            fn ($attribute) => [$attribute->attribute_name => $attribute->value],
                        )->all(),
                        'image_path' => $this->primaryImagePath($variant, $product),
                    ],
                    'unit_price' => $variant->price,
                    'quantity' => $item->quantity,
                ]);

                ReserveStockForOrder::run($variant, $item->quantity, $order);
            }

            if ($couponCode !== null) {
                ApplyCouponToOrder::run($order, $couponCode);
                $order->refresh();
            }

            return $order;
        });
    }

    private function primaryImagePath(ProductVariant $variant, Product $product): ?string
    {
        foreach ($variant->images as $image) {
            if ($image->is_primary) {
                return $image->path;
            }
        }

        foreach ($product->images as $image) {
            if ($image->is_primary) {
                return $image->path;
            }
        }

        return null;
    }
}
