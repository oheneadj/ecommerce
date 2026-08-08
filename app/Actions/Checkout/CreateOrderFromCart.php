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
use App\Models\StoreSetting;
use App\Notifications\OrderPlaced;
use App\Notifications\Support\OrderRecipient;
use App\Notifications\Support\SafeNotifier;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The Cart row is locked for the duration of the transaction — a cart
 * converts into at most one order (`orders.cart_id` is unique), and the
 * existing-order check by itself is a check-then-write race: two
 * simultaneous checkout submissions for the same cart (double-click,
 * back-button resubmit) could both read no existing order and both try to
 * create one, with the second hitting a raw unique-constraint violation
 * instead of gracefully returning the first one's result. Locking the
 * cart row and re-checking inside the lock serializes the two attempts,
 * so the second always sees the first's already-created order.
 *
 * The rest of the locking happens inside the nested
 * ReserveStockForOrder/ApplyCouponToOrder calls this Action makes
 * (AGENTS.md Section 4a). Writes: Order (with `address_snapshot`) +
 * OrderItem rows (with `item_snapshot`) + StockReservation + CouponUsage.
 *
 * Price is always read from the variant's *current* state at this moment,
 * never from whatever was true when the item was added to the cart — the
 * cart never locks in a price (BRD Principle 8). `item_snapshot` then
 * permanently freezes that same data on the OrderItem so a later product
 * edit/archive/delete can never change how a past order displays.
 *
 * A guest order's `user_id` is never set from a matching `guest_email` —
 * that would silently attach someone else's order to an account on a
 * coincidental email match. Linking only ever happens via ClaimGuestOrder,
 * which requires the customer to authenticate first (BRD FR-3.2a).
 *
 * The order-placed notification fires only `->afterCommit()`, never inside
 * this transaction — a rollback can't un-send an SMS. A delivery failure
 * never fails checkout itself (SafeNotifier logs and swallows it).
 *
 * `tax_total` is computed once here, from `StoreSetting::current()->tax_rate`
 * against the pre-discount `subtotal` — a single, uniform, whole-number
 * percentage (Epic E13.4), matching how a later `ApplyCouponToOrder` call
 * folds it into `grand_total` unchanged (a coupon discounts the sale price,
 * never the tax already computed on it).
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
        return DB::transaction(function () use ($cart, $address, $guestEmail, $guestPhone, $couponCode): Order {
            $lockedCart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            $existing = $lockedCart->order;

            if ($existing !== null) {
                return $existing;
            }

            $items = $cart->items()->with('productVariant.product.brand', 'productVariant.attributeValues', 'productVariant.images', 'productVariant.product.images')->get();

            if ($items->isEmpty()) {
                throw new EmptyCartException;
            }

            $subtotal = $items->sum(fn ($item) => $item->productVariant->price * $item->quantity);
            $taxTotal = (int) round($subtotal * StoreSetting::current()->tax_rate / 100);

            $order = Order::query()->create([
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'guest_email' => $cart->user_id === null ? $guestEmail : null,
                'guest_phone' => $cart->user_id === null ? $guestPhone : null,
                'address_id' => $address->id,
                'address_snapshot' => [
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'line1' => $address->line1,
                    'line2' => $address->line2,
                    'city' => $address->city,
                    'region' => $address->region,
                ],
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => $taxTotal,
                'shipping_total' => 0,
                'grand_total' => $subtotal + $taxTotal,
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

            DB::afterCommit(fn () => SafeNotifier::send(OrderRecipient::for($order), new OrderPlaced($order)));

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
