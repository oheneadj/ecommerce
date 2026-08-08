<?php

/**
 * Covers /theme.css — the per-deployment brand-color stylesheet, generated
 * from StoreSetting rather than baked into the Vite build, so an admin's
 * color change takes effect with no rebuild (Epic E13.2).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeCssTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_stores_brand_colors_as_real_css(): void
    {
        StoreSetting::current()->update(['primary_color' => '#ff0000', 'secondary_color' => '#00ff00']);

        $response = $this->get('/theme.css');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $response->assertSee('--color-brand-primary: #ff0000;', false);
        $response->assertSee('--color-brand-secondary: #00ff00;', false);
    }

    public function test_changing_the_brand_color_is_reflected_immediately_not_after_a_ttl(): void
    {
        StoreSetting::current()->update(['primary_color' => '#111111']);
        $this->get('/theme.css')->assertSee('#111111', false);

        StoreSetting::current()->update(['primary_color' => '#222222']);

        $response = $this->get('/theme.css');
        $response->assertSee('#222222', false);
        $response->assertDontSee('#111111', false);
    }
}
