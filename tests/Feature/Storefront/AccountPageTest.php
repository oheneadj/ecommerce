<?php

/**
 * Covers the customer account dashboard (/account) — a themed hub distinct
 * from /settings/profile's actual profile-editing form.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\RecentOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_an_authenticated_customer_can_view_their_account_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/account')->assertOk();
    }

    public function test_a_phone_only_customer_with_no_name_can_view_their_account_page(): void
    {
        $user = User::factory()->create(['name' => null, 'email' => null, 'phone' => '+233201234567']);
        $this->actingAs($user);

        $this->get('/account')->assertOk();
    }

    public function test_it_shows_only_the_authenticated_customers_own_recent_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownOrder = Order::factory()->create(['user_id' => $user->id, 'order_number' => 'ORD-2026-000001']);
        Order::factory()->create(['user_id' => $otherUser->id, 'order_number' => 'ORD-2026-000002']);

        $this->actingAs($user);

        // #[Lazy] means the real component only renders past its own
        // `$refresh` follow-up request — same forced-hydration pattern
        // CartPageTest uses for its own #[Lazy] component.
        Livewire::test(RecentOrders::class)
            ->call('$refresh')
            ->assertSee($ownOrder->order_number)
            ->assertDontSee('ORD-2026-000002');
    }

    /**
     * The recent-orders query only ever runs on the follow-up request the
     * #[Lazy] attribute defers to — the initial HTTP response (a
     * customer's very first paint) must show the skeleton, never a blank
     * gap while that request is in flight.
     */
    public function test_the_recent_orders_widget_shows_a_skeleton_placeholder_before_it_loads(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account')->assertOk()->assertSeeHtml('animate-pulse');
    }

    public function test_it_links_to_the_profile_settings_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account')->assertSee(route('profile.edit'), false);
    }
}
