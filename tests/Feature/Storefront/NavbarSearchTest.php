<?php

/**
 * Covers the storefront navbar's always-visible search box
 * (`layouts/storefront.blade.php`) — a plain GET form to `/products`, which
 * `ProductListingPage` already filters by via its `#[Url]`-bound `search`
 * property, so no new search logic is introduced here.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_navbar_shows_a_search_box_on_every_storefront_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeHtml('name="search"')
            ->assertSeeHtml('action="'.route('products.index').'"');
    }

    public function test_submitting_the_navbar_search_filters_the_product_listing(): void
    {
        $matching = Product::factory()->create(['name' => 'Blue Sneakers', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $matching->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $other = Product::factory()->create(['name' => 'Red Hat', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $other->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->get('/products?search=Sneakers')
            ->assertOk()
            ->assertSee('Blue Sneakers')
            ->assertDontSee('Red Hat');
    }

    /**
     * Regression: the navbar search previously used <x-input>, whose
     * error-directive depends on the $errors view-share — only bound
     * during the normal middleware pipeline. Error pages render outside
     * it but still use this same layout, so that crashed every 404 with
     * an undefined $errors variable.
     */
    public function test_a_404_page_still_renders_with_the_navbar_present(): void
    {
        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertSeeHtml('name="search"');
    }
}
