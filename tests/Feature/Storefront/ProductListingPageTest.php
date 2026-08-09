<?php

/**
 * Covers the public product listing/search page (/products) — reactive
 * search, category, brand, and price filters.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Livewire\Storefront\ProductListingPage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductListingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $variantAttributes
     */
    private function purchasableProduct(array $attributes = [], array $variantAttributes = []): Product
    {
        $product = Product::factory()->create(array_merge(['status' => ProductStatus::Active], $attributes));
        ProductVariant::factory()->create(array_merge([
            'product_id' => $product->id,
            'status' => VariantStatus::Active,
            'stock' => 5,
        ], $variantAttributes));

        return $product;
    }

    public function test_anyone_can_view_the_product_listing(): void
    {
        $this->get('/products')->assertOk();
    }

    public function test_the_listing_shows_purchasable_products(): void
    {
        $this->purchasableProduct(['name' => 'Red Shirt']);

        Livewire::test(ProductListingPage::class)->assertSee('Red Shirt');
    }

    public function test_the_listing_never_shows_an_archived_product(): void
    {
        $this->purchasableProduct(['name' => 'Archived Item', 'status' => ProductStatus::Archived]);

        Livewire::test(ProductListingPage::class)->assertDontSee('Archived Item');
    }

    public function test_searching_filters_products_by_name(): void
    {
        $this->purchasableProduct(['name' => 'Blue Sneakers']);
        $this->purchasableProduct(['name' => 'Green Hat']);

        Livewire::test(ProductListingPage::class)
            ->set('search', 'Sneakers')
            ->assertSee('Blue Sneakers')
            ->assertDontSee('Green Hat');
    }

    public function test_filtering_by_category_only_shows_that_categorys_products(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();
        $this->purchasableProduct(['name' => 'In Category A', 'category_id' => $categoryA->id]);
        $this->purchasableProduct(['name' => 'In Category B', 'category_id' => $categoryB->id]);

        Livewire::test(ProductListingPage::class)
            ->set('category', $categoryA->slug)
            ->assertSee('In Category A')
            ->assertDontSee('In Category B');
    }

    public function test_filtering_by_brand_only_shows_that_brands_products(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        $this->purchasableProduct(['name' => 'Brand A Item', 'brand_id' => $brandA->id]);
        $this->purchasableProduct(['name' => 'Brand B Item', 'brand_id' => $brandB->id]);

        Livewire::test(ProductListingPage::class)
            ->set('brand', $brandA->slug)
            ->assertSee('Brand A Item')
            ->assertDontSee('Brand B Item');
    }

    public function test_filtering_by_price_range_excludes_products_outside_it(): void
    {
        $this->purchasableProduct(['name' => 'Cheap Item'], ['price' => 1000]);
        $this->purchasableProduct(['name' => 'Expensive Item'], ['price' => 10000]);

        Livewire::test(ProductListingPage::class)
            ->set('minPrice', 50)
            ->set('maxPrice', 200)
            ->assertDontSee('Cheap Item')
            ->assertSee('Expensive Item');
    }

    public function test_a_product_with_no_stock_never_appears_in_the_listing(): void
    {
        $product = Product::factory()->create(['name' => 'Out Of Stock Item', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 0]);

        Livewire::test(ProductListingPage::class)->assertDontSee('Out Of Stock Item');
    }
}
