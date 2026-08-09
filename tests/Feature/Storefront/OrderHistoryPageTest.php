<?php

/**
 * Covers the customer-facing order history page (/account/orders).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\OrderHistoryPage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/account/orders')->assertRedirect('/login');
    }

    public function test_an_authenticated_customer_can_view_an_empty_order_history(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account/orders')->assertOk()->assertSee("haven't placed any orders");
    }

    public function test_the_order_history_page_shows_the_customers_own_orders(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        Livewire::test(OrderHistoryPage::class)
            ->assertSee($order->order_number);
    }

    public function test_a_customer_never_sees_another_customers_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id]);
        $this->actingAs($user);

        Livewire::test(OrderHistoryPage::class)
            ->assertDontSee($otherOrder->order_number);
    }
}
