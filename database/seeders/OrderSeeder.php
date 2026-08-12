<?php

/**
 * Seeds realistic orders by driving the real checkout/payment/fulfillment
 * Actions (not raw inserts), so the resulting stock movements, reservations,
 * activity log, and notifications all reflect genuine business logic.
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Checkout\CreateOrderFromCart;
use App\Actions\Order\AssignShipment;
use App\Actions\Order\UpdateOrderStatus;
use App\Actions\Payment\MarkPaymentFailed;
use App\Actions\Payment\SettlePaymentSuccess;
use App\Actions\Review\SubmitReview;
use App\Actions\Wishlist\AddToWishlist as AddVariantToWishlist;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::whereDoesntHave('roles')->get();
        $variantsInStock = ProductVariant::query()->where('stock', '>', 0)->get();
        $shippingMethod = ShippingMethod::query()->first();

        if ($customers->isEmpty() || $variantsInStock->isEmpty() || $shippingMethod === null) {
            return;
        }

        $this->pendingOrder($customers[0], $variantsInStock);
        $this->paidOrder($customers[1] ?? $customers[0], $variantsInStock);
        $this->shippedOrder($customers[2] ?? $customers[0], $variantsInStock, $shippingMethod);
        $this->deliveredOrderWithReview($customers[3] ?? $customers[0], $variantsInStock, $shippingMethod);
        $this->cancelledOrder($customers[0], $variantsInStock);
        $this->failedPaymentOrder($customers[1] ?? $customers[0], $variantsInStock);
        $this->guestOrder($variantsInStock);

        $this->wishlist($customers[0], $variantsInStock);
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function cart(User $user, Collection $variants): Cart
    {
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variants->random()->id,
            'quantity' => 1,
        ]);

        return $cart;
    }

    private function address(User $user): Address
    {
        return Address::factory()->create(['user_id' => $user->id]);
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function pendingOrder(User $user, Collection $variants): void
    {
        CreateOrderFromCart::run($this->cart($user, $variants), $this->address($user));
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function paidOrder(User $user, Collection $variants): void
    {
        $order = CreateOrderFromCart::run($this->cart($user, $variants), $this->address($user));

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'moolre',
            'amount' => $order->grand_total,
            'provider_reference' => 'seed-'.$order->order_number,
            'status' => PaymentStatus::Pending,
        ]);

        SettlePaymentSuccess::run($payment);
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function shippedOrder(User $user, Collection $variants, ShippingMethod $shippingMethod): void
    {
        $order = CreateOrderFromCart::run($this->cart($user, $variants), $this->address($user));

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'amount' => $order->grand_total,
            'provider_reference' => 'seed-'.$order->order_number,
            'status' => PaymentStatus::Pending,
        ]);

        SettlePaymentSuccess::run($payment);
        AssignShipment::run($order->fresh(), $shippingMethod, 'GH-TRACK-'.$order->id);
        UpdateOrderStatus::run($order->fresh(), OrderStatus::Shipped, note: 'Handed to courier (seed data).');
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function deliveredOrderWithReview(User $user, Collection $variants, ShippingMethod $shippingMethod): void
    {
        $order = CreateOrderFromCart::run($this->cart($user, $variants), $this->address($user));

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'moolre',
            'amount' => $order->grand_total,
            'provider_reference' => 'seed-'.$order->order_number,
            'status' => PaymentStatus::Pending,
        ]);

        SettlePaymentSuccess::run($payment);
        AssignShipment::run($order->fresh(), $shippingMethod, 'GH-TRACK-'.$order->id);
        UpdateOrderStatus::run($order->fresh(), OrderStatus::Shipped);
        $order = UpdateOrderStatus::run($order->fresh(), OrderStatus::Delivered, note: 'Delivered to customer (seed data).');

        $item = $order->items()->first();

        if ($item !== null) {
            SubmitReview::run($user, $item, 5, 'Exactly as described, arrived quickly. Very happy with this purchase.', 'Great buy');
        }
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function cancelledOrder(User $user, Collection $variants): void
    {
        $order = CreateOrderFromCart::run($this->cart($user, $variants), $this->address($user));

        UpdateOrderStatus::run($order, OrderStatus::Cancelled, note: 'Customer requested cancellation (seed data).');
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function failedPaymentOrder(User $user, Collection $variants): void
    {
        $order = CreateOrderFromCart::run($this->cart($user, $variants), $this->address($user));

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'amount' => $order->grand_total,
            'provider_reference' => 'seed-'.$order->order_number,
            'status' => PaymentStatus::Pending,
        ]);

        MarkPaymentFailed::run($payment);
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function guestOrder(Collection $variants): void
    {
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => 'seed-guest-session']);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variants->random()->id,
            'quantity' => 1,
        ]);

        $address = Address::factory()->create(['user_id' => null]);

        CreateOrderFromCart::run($cart, $address, guestEmail: 'guest-seed@example.com', guestPhone: '0559999999');
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function wishlist(User $user, Collection $variants): void
    {
        foreach ($variants->random(min(2, $variants->count())) as $variant) {
            AddVariantToWishlist::run($user, $variant);
        }
    }
}
