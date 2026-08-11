<?php

/**
 * Covers the navbar's Account menu (`layouts/storefront.blade.php`) — a
 * dropdown with "My Account" and "Log out" for authenticated customers,
 * since the storefront previously had no logout UI at all (it lived on the
 * old starter-kit sidebar, removed along with /dashboard).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarAccountMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_customer_sees_a_logout_option_in_the_navbar(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')
            ->assertOk()
            ->assertSee('Log out')
            ->assertSeeHtml('action="'.route('logout').'"');
    }

    public function test_a_guest_sees_no_logout_option_in_the_navbar(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Log out');
    }

    public function test_logging_out_from_the_navbar_actually_logs_the_customer_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
