<?php

/**
 * Covers the dynamic /robots.txt route.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Tests\TestCase;

class RobotsControllerTest extends TestCase
{
    public function test_it_disallows_private_and_admin_only_areas(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $response->assertSee('Disallow: /cart', false);
        $response->assertSee('Disallow: /checkout', false);
        $response->assertSee('Disallow: /account', false);
        $response->assertSee('Disallow: /wishlist', false);
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Disallow: /admin', false);
    }

    public function test_it_references_the_sitemap_by_absolute_url(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }
}
