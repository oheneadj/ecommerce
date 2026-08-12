<?php

/**
 * Covers the cookie consent banner — present on every storefront and auth
 * page, dismissed via localStorage so it never nags a visitor who already
 * accepted it.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_banner_is_present_on_the_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('We use cookies')
            ->assertSeeHtml("localStorage.getItem('cookie-consent-accepted')");
    }

    public function test_the_banner_is_present_on_the_login_page(): void
    {
        $this->get('/login/phone')
            ->assertOk()
            ->assertSee('We use cookies');
    }

    public function test_accepting_stores_the_decision_in_local_storage(): void
    {
        $this->get('/')
            ->assertSeeHtml("localStorage.setItem('cookie-consent-accepted', '1')");
    }
}
