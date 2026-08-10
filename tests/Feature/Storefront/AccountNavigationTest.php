<?php

/**
 * Covers the customer account area's unified navigation (Dashboard, Orders,
 * Addresses, Wishlist, Profile, Security, Appearance) — shared across every
 * /account/* and /settings/* page via <x-account.layout> — and confirms the
 * old standalone /dashboard route (replaced by /account) is gone.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_dashboard_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('dashboard'));
    }

    public function test_the_account_nav_links_to_every_account_area_page(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/account');

        $response->assertOk();
        $response->assertSee(route('account.show'), false);
        $response->assertSee(route('account.orders'), false);
        $response->assertSee(route('account.addresses'), false);
        $response->assertSee(route('wishlist.show'), false);
        $response->assertSee(route('profile.edit'), false);
        $response->assertSee(route('security.edit'), false);
        $response->assertSee(route('appearance.edit'), false);
    }

    public function test_the_settings_pages_use_the_storefront_layout_with_the_account_nav(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/settings/profile')
            ->assertOk()
            ->assertSee(route('account.show'), false)
            ->assertSee(route('security.edit'), false);
    }
}
