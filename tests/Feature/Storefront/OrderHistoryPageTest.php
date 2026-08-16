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

        // #[Lazy] means the real component only renders past its own
        // `$refresh` follow-up request — same forced-hydration pattern
        // CartPageTest uses for its own #[Lazy] component.
        Livewire::test(OrderHistoryPage::class)->call('$refresh')->assertSee("haven't placed any orders");
    }

    /**
     * The real content only ever reaches the page through a follow-up
     * request the #[Lazy] attribute defers to — the initial HTTP response
     * (what a customer's very first paint actually sees) must show the
     * skeleton, never a blank gap while that request is in flight.
     */
    public function test_the_page_shows_a_skeleton_placeholder_before_the_real_component_loads(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account/orders')->assertOk()->assertSeeHtml('animate-pulse');
    }

    public function test_the_order_history_page_shows_the_customers_own_orders(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        Livewire::test(OrderHistoryPage::class)
            ->call('$refresh')
            ->assertSee($order->order_number);
    }

    public function test_a_customer_never_sees_another_customers_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id]);
        $this->actingAs($user);

        Livewire::test(OrderHistoryPage::class)
            ->call('$refresh')
            ->assertDontSee($otherOrder->order_number);
    }
}
