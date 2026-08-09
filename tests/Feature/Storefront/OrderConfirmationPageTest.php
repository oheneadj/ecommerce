<?php

/**
 * Covers the customer-facing order confirmation page shown right after
 * checkout (/orders/{order}/confirmation).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\OrderConfirmationPage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderConfirmationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_view_their_own_guest_order_confirmation(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);

        $this->get("/orders/{$order->ulid}/confirmation")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Thank you for your order');
    }

    public function test_a_guest_cannot_view_a_registered_customers_order_confirmation(): void
    {
        $order = Order::factory()->create();

        $this->get("/orders/{$order->ulid}/confirmation")->assertNotFound();
    }

    public function test_a_customer_sees_their_own_order_confirmation(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->get("/orders/{$order->ulid}/confirmation")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Thank you for your order');
    }

    public function test_a_customer_cannot_view_another_customers_order_confirmation(): void
    {
        $user = User::factory()->create();
        $otherOrder = Order::factory()->create();
        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(OrderConfirmationPage::class, ['orderUlid' => $otherOrder->ulid]);
    }
}
