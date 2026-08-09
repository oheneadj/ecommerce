<?php

/**
 * Covers the branded 403/404/500 error pages — reskinned with the shared
 * storefront layout instead of Laravel's default error views.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_shows_the_branded_404_page(): void
    {
        config(['app.debug' => false]);

        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertSee('This page could not be found');
    }
}
