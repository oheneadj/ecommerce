<?php

declare(strict_types=1);

namespace Tests\Feature\Wishlist;

use App\Actions\Payment\HandleLatePaymentConfirmation;
use App\Actions\Payment\SettlePaymentSuccess;
use App\Actions\Wishlist\AddToWishlist;
use App\Actions\Wishlist\RemoveFromWishlist;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockReservationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_add_a_variant_to_their_wishlist(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        AddToWishlist::run($user, $variant);

        $this->assertSame(1, $user->wishlistItems()->count());
    }

    public function test_adding_the_same_variant_twice_is_not_a_duplicate(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        AddToWishlist::run($user, $variant);
        AddToWishlist::run($user, $variant);

        $this->assertSame(1, $user->wishlistItems()->count());
    }

    public function test_customer_can_remove_a_variant_from_their_wishlist(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        AddToWishlist::run($user, $variant);

        RemoveFromWishlist::run($user, $variant);

        $this->assertSame(0, $user->wishlistItems()->count());
    }

    public function test_wishlist_is_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        AddToWishlist::run($userA, $variant);

        $this->assertSame(1, $userA->wishlistItems()->count());
        $this->assertSame(0, $userB->wishlistItems()->count());
    }

    public function test_paying_for_an_order_removes_its_items_from_the_buyers_wishlist(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        AddToWishlist::run($user, $variant);

        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'status' => StockReservationStatus::Active,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'fake',
            'status' => PaymentStatus::Pending,
        ]);

        SettlePaymentSuccess::run($payment);

        $this->assertSame(0, $user->wishlistItems()->count());
    }

    public function test_late_payment_confirmation_with_stock_still_available_removes_the_wishlist_item_too(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddToWishlist::run($user, $variant);

        // No active StockReservation for this order → SettlePaymentSuccess
        // delegates to HandleLatePaymentConfirmation.
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'fake',
            'status' => PaymentStatus::Pending,
        ]);

        SettlePaymentSuccess::run($payment);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(0, $user->wishlistItems()->count());
    }

    public function test_late_payment_confirmation_without_stock_does_not_touch_the_wishlist(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        AddToWishlist::run($user, $variant);

        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'fake',
            'amount' => 1000,
            'status' => PaymentStatus::Pending,
        ]);

        HandleLatePaymentConfirmation::run($payment);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(1, $user->wishlistItems()->count());
    }

    public function test_a_guest_orders_payment_never_touches_another_customers_wishlist(): void
    {
        $wishlistOwner = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        AddToWishlist::run($wishlistOwner, $variant);

        $guestOrder = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
            'status' => OrderStatus::Pending,
        ]);
        OrderItem::factory()->create([
            'order_id' => $guestOrder->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $guestOrder->id,
            'quantity' => 1,
            'status' => StockReservationStatus::Active,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $guestOrder->id,
            'provider' => 'fake',
            'status' => PaymentStatus::Pending,
        ]);

        SettlePaymentSuccess::run($payment);

        $this->assertSame(1, $wishlistOwner->wishlistItems()->count());
    }
}
