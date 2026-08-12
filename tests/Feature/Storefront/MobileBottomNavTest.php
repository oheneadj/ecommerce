<?php

/**
 * Covers the fixed mobile bottom tab bar (partials/mobile-bottom-nav.blade.php)
 * — primary navigation added alongside the top row's Bell/Cart.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileBottomNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bottom_nav_links_to_every_primary_destination(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeHtml('href="'.route('home').'"');
        $response->assertSeeHtml('href="'.route('products.index').'"');
        $response->assertSeeHtml('href="'.route('wishlist.show').'"');
        $response->assertSeeHtml('href="'.route('account.show').'"');
    }

    public function test_the_current_page_is_highlighted_as_active(): void
    {
        $response = $this->get('/products');

        $response->assertOk();
        $response->assertSeeHtml('aria-current="page"');
        $this->assertSame(1, substr_count((string) $response->getContent(), 'aria-current="page"'));
    }

    public function test_the_home_tab_is_active_on_the_homepage_not_shop(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // Exactly one aria-current="page" in the whole response — the Home
        // tab's — confirms the Shop tab isn't also (mis)marked active.
        $response->assertSeeHtml('aria-current="page"');
        $this->assertSame(1, substr_count((string) $response->getContent(), 'aria-current="page"'));
    }
}
