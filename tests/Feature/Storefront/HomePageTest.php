<?php

/**
 * Covers the public storefront homepage (/).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_the_homepage(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_the_homepage_shows_categories_and_purchasable_products(): void
    {
        $category = Category::factory()->create(['name' => 'Sneakers']);
        $product = Product::factory()->create(['name' => 'Blue Sneakers', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Sneakers')
            ->assertSee('Blue Sneakers');
    }

    public function test_the_homepage_never_shows_a_product_with_no_stock(): void
    {
        $product = Product::factory()->create(['name' => 'Out Of Stock Item', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 0]);

        $this->get('/')->assertOk()->assertDontSee('Out Of Stock Item');
    }

    /**
     * Regression: Eloquent's lazy-loading guard only activates when a
     * relation batch-hydrates more than one row at once (`Builder::hydrate()`
     * skips it for a single row) — so this needs at least two products,
     * each with a variant and no images of its own, to actually reproduce
     * the "Attempted to lazy load [images] on model [ProductVariant]"
     * violation that a single-product/single-variant fixture would miss.
     */
    public function test_the_homepage_does_not_lazy_load_variant_images_when_a_product_has_no_images_of_its_own(): void
    {
        foreach (['First Item', 'Second Item'] as $name) {
            $product = Product::factory()->create(['name' => $name, 'status' => ProductStatus::Active]);
            ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);
        }

        $this->get('/')
            ->assertOk()
            ->assertSee('First Item')
            ->assertSee('Second Item');
    }

    public function test_the_homepage_never_shows_a_draft_product(): void
    {
        $product = Product::factory()->create(['name' => 'Draft Item', 'status' => ProductStatus::Draft]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->get('/')->assertOk()->assertDontSee('Draft Item');
    }
}
