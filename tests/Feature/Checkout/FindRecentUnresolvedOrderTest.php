<?php

/**
 * Covers FindRecentUnresolvedOrder — the "customer navigated back to an
 * empty-looking cart while a payment was still in flight" safety net.
 */

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\GetCurrentCart;
use App\Actions\Cart\ResolveCurrentCart;
use App\Actions\Checkout\FindRecentUnresolvedOrder;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindRecentUnresolvedOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_an_order_whose_only_payment_is_pending(): void
    {
        $user = User::factory()->create();
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, ProductVariant::factory()->create(['stock' => 10]), 1);
        $order = Order::factory()->create(['user_id' => $user->id, 'cart_id' => $cart->id]);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Pending]);

        $found = FindRecentUnresolvedOrder::run($user, 'irrelevant');

        $this->assertSame($order->id, $found?->id);
    }

    public function test_it_finds_an_order_whose_only_payment_failed(): void
    {
        $user = User::factory()->create();
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, ProductVariant::factory()->create(['stock' => 10]), 1);
        $order = Order::factory()->create(['user_id' => $user->id, 'cart_id' => $cart->id]);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Failed]);

        $found = FindRecentUnresolvedOrder::run($user, 'irrelevant');

        $this->assertSame($order->id, $found?->id);
    }

    public function test_it_ignores_an_order_that_already_succeeded(): void
    {
        $user = User::factory()->create();
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, ProductVariant::factory()->create(['stock' => 10]), 1);
        $order = Order::factory()->create(['user_id' => $user->id, 'cart_id' => $cart->id]);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Success]);

        $this->assertNull(FindRecentUnresolvedOrder::run($user, 'irrelevant'));
    }

    public function test_it_returns_null_when_there_is_no_recent_cart_at_all(): void
    {
        $user = User::factory()->create();

        $this->assertNull(FindRecentUnresolvedOrder::run($user, 'irrelevant'));
    }

    public function test_it_finds_a_guest_order_by_session_id(): void
    {
        $sessionId = 'guest-session-abc';
        $cart = ResolveCurrentCart::run(null, $sessionId);
        AddItemToCart::run($cart, ProductVariant::factory()->create(['stock' => 10]), 1);
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'a@example.com', 'cart_id' => $cart->id]);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Failed]);

        $found = FindRecentUnresolvedOrder::run(null, $sessionId);

        $this->assertSame($order->id, $found?->id);
    }

    public function test_it_only_returns_the_most_recent_unresolved_order(): void
    {
        $user = User::factory()->create();

        $cart1 = GetCurrentCart::run($user);
        AddItemToCart::run($cart1, ProductVariant::factory()->create(['stock' => 10]), 1);
        $olderOrder = Order::factory()->create(['user_id' => $user->id, 'cart_id' => $cart1->id]);
        Payment::factory()->create(['order_id' => $olderOrder->id, 'status' => PaymentStatus::Failed]);

        // Success closes cart1, forcing a fresh cart for the next attempt.
        Payment::factory()->create(['order_id' => $olderOrder->id, 'status' => PaymentStatus::Success]);

        $cart2 = GetCurrentCart::run($user);
        $this->assertNotSame($cart1->id, $cart2->id);
        AddItemToCart::run($cart2, ProductVariant::factory()->create(['stock' => 10]), 1);
        $newerOrder = Order::factory()->create(['user_id' => $user->id, 'cart_id' => $cart2->id]);
        Payment::factory()->create(['order_id' => $newerOrder->id, 'status' => PaymentStatus::Failed]);

        $found = FindRecentUnresolvedOrder::run($user, 'irrelevant');

        $this->assertSame($newerOrder->id, $found?->id);
    }
}
