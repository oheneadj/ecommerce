<?php

/**
 * Covers the storefront navbar's search box
 * (`<livewire:storefront.search-autosuggest />`, embedded in
 * `layouts/storefront.blade.php`) — the lazy-loaded placeholder shown on
 * first paint, and that a plain full-listing search still works via the
 * `?search=` query param `ProductListingPage` reads. Live-suggestion
 * behavior itself is covered by SearchAutosuggestTest.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Livewire\Storefront\SearchAutosuggest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavbarSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_navbar_shows_a_search_box_placeholder_on_every_storefront_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeHtml('placeholder="Search products');
    }

    public function test_the_mounted_search_component_renders_a_real_search_form(): void
    {
        // A #[Lazy] component's very first render is its placeholder (see
        // search-autosuggest-placeholder.blade.php) — any interaction
        // after that mounts the real component, same as the browser's
        // automatic lazy follow-up request.
        Livewire::test(SearchAutosuggest::class)
            ->set('query', '')
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
            ->assertSeeHtml('placeholder="Search products');
    }
}
