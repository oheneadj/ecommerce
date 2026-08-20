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
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    /**
     * On mobile the sidebar becomes a slide-over rather than stacking
     * above the product grid — this asserts the trigger button, the
     * Alpine open-state, and the backdrop all exist in the markup (real
     * open/close interaction is Alpine-driven client-side, not something
     * a server-rendered HTTP test can exercise).
     */
    public function test_the_mobile_filters_slide_over_markup_is_present(): void
    {
        $response = $this->get('/products');

        $response->assertOk();
        $response->assertSee(__('Filters'));
        $response->assertSeeHtml('x-data="{ filtersOpen: false }"');
        $response->assertSeeHtml('@click="filtersOpen = true"');
        $response->assertSeeHtml('@click="filtersOpen = false"');
    }

    public function test_the_listing_shows_purchasable_products(): void
    {
        $this->purchasableProduct(['name' => 'Red Shirt']);

        Livewire::test(ProductListingPage::class)->assertSee('Red Shirt');
    }

    /**
     * Regression/SEO-safety: real product content must be present in the
     * plain, unauthenticated initial HTTP response (not hidden behind a
     * wire:loading skeleton that only resolves client-side) — that's what
     * keeps this page crawlable. The filter-change skeleton itself is a
     * client-side visibility toggle (Livewire's [wire:loading] CSS), not
     * something a server-rendered HTML diff can observe — this only
     * confirms the skeleton markup exists and is correctly scoped via
     * wire:target to the filter inputs, not the whole page.
     */
    public function test_the_initial_page_load_shows_real_products_not_the_filter_change_skeleton(): void
    {
        $this->purchasableProduct(['name' => 'Red Shirt']);

        $response = $this->get('/products');

        $response->assertOk();
        $response->assertSee('Red Shirt');
        $response->assertSeeHtml('wire:target="search,category,brand,minPrice,maxPrice,toggleAttributeTerm,resetFilters"');
        $response->assertSeeHtml('wire:loading.class="opacity-50"');
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

    public function test_a_brand_with_a_logo_shows_it_in_the_filter_and_a_brand_without_one_shows_a_fallback_icon(): void
    {
        $withLogo = Brand::factory()->create(['logo_path' => 'brand-logos/nike.png']);
        $withoutLogo = Brand::factory()->create(['logo_path' => null]);
        $this->purchasableProduct(['brand_id' => $withLogo->id]);
        $this->purchasableProduct(['brand_id' => $withoutLogo->id]);

        $response = Livewire::test(ProductListingPage::class);

        $response->assertSeeHtml(Storage::disk('public')->url('brand-logos/nike.png'));
        // The "folder" icon's own SVG path — the fallback icon rendered
        // for a brand with no logo (see app-icon.blade.php).
        $response->assertSeeHtml('M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15');
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

    /**
     * Regression test: minPrice/maxPrice used to be applied as two
     * independent `whereHas('variants', ...)` calls, so a product with one
     * variant below the range and another above it satisfied both
     * conditions separately even though no single variant was actually
     * within the range.
     */
    public function test_a_product_is_not_matched_by_two_different_variants_each_satisfying_only_one_price_bound(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active, 'name' => 'Split Variant Product']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5, 'price' => 1000]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5, 'price' => 50000]);

        Livewire::test(ProductListingPage::class)
            ->set('minPrice', 100)
            ->set('maxPrice', 200)
            ->assertDontSee('Split Variant Product');
    }

    public function test_the_price_slider_ceiling_matches_the_most_expensive_active_in_stock_variant(): void
    {
        $this->purchasableProduct(['name' => 'Cheap Item'], ['price' => 1000]);
        $this->purchasableProduct(['name' => 'Priciest Item'], ['price' => 15099]);

        // A variant that's out of stock shouldn't be able to push the
        // ceiling higher than what's actually purchasable right now.
        $unreachable = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $unreachable->id, 'status' => VariantStatus::Active, 'stock' => 0, 'price' => 99999]);

        $component = Livewire::test(ProductListingPage::class);

        $this->assertSame(151.0, $component->instance()->catalogMaxPrice());
    }

    public function test_the_price_slider_ceiling_falls_back_when_the_catalog_is_empty(): void
    {
        $component = Livewire::test(ProductListingPage::class);

        $this->assertSame(1000.0, $component->instance()->catalogMaxPrice());
    }

    public function test_a_product_with_no_stock_never_appears_in_the_listing(): void
    {
        $product = Product::factory()->create(['name' => 'Out Of Stock Item', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 0]);

        Livewire::test(ProductListingPage::class)->assertDontSee('Out Of Stock Item');
    }

    public function test_an_attribute_with_no_terms_in_use_anywhere_shows_no_filter_group(): void
    {
        $this->purchasableProduct(['name' => 'Plain Item']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Purple']);
        // "Purple" exists as a term but no variant anywhere carries it.

        Livewire::test(ProductListingPage::class)
            ->assertSet('filterableAttributes', fn ($attributes) => $attributes->isEmpty());
    }

    public function test_selecting_a_color_term_filters_to_only_matching_products(): void
    {
        $color = Attribute::factory()->create(['name' => 'Color']);
        $red = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Red']);
        $blue = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Blue']);

        $redProduct = $this->purchasableProduct(['name' => 'Red Shirt']);
        $redProduct->variants->first()->attributeTerms()->attach($red->id);

        $blueProduct = $this->purchasableProduct(['name' => 'Blue Shirt']);
        $blueProduct->variants->first()->attributeTerms()->attach($blue->id);

        Livewire::test(ProductListingPage::class)
            ->call('toggleAttributeTerm', $color->id, $red->id)
            ->assertSee('Red Shirt')
            ->assertDontSee('Blue Shirt');
    }

    /**
     * Selecting Color=Red AND Size=M must require a single variant that is
     * BOTH — not a product that merely has some Red variant and some
     * unrelated M variant.
     */
    public function test_selecting_two_attributes_requires_the_same_variant_to_match_both(): void
    {
        $color = Attribute::factory()->create(['name' => 'Color']);
        $size = Attribute::factory()->create(['name' => 'Size']);
        $red = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Red']);
        $blue = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Blue']);
        $medium = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'M']);

        // Genuinely matches both together.
        $trueMatch = Product::factory()->create(['name' => 'Red Medium Shirt', 'status' => ProductStatus::Active]);
        $trueMatchVariant = ProductVariant::factory()->create(['product_id' => $trueMatch->id, 'status' => VariantStatus::Active, 'stock' => 5]);
        $trueMatchVariant->attributeTerms()->attach([$red->id, $medium->id]);

        // Has a Red variant and an M variant, but never both on the same variant.
        $falseMatch = Product::factory()->create(['name' => 'Mixed Shirt', 'status' => ProductStatus::Active]);
        $falseMatchRedVariant = ProductVariant::factory()->create(['product_id' => $falseMatch->id, 'status' => VariantStatus::Active, 'stock' => 5]);
        $falseMatchRedVariant->attributeTerms()->attach($red->id);
        $falseMatchBlueMediumVariant = ProductVariant::factory()->create(['product_id' => $falseMatch->id, 'status' => VariantStatus::Active, 'stock' => 5]);
        $falseMatchBlueMediumVariant->attributeTerms()->attach([$blue->id, $medium->id]);

        Livewire::test(ProductListingPage::class)
            ->call('toggleAttributeTerm', $color->id, $red->id)
            ->call('toggleAttributeTerm', $size->id, $medium->id)
            ->assertSee('Red Medium Shirt')
            ->assertDontSee('Mixed Shirt');
    }

    public function test_picking_a_category_narrows_which_color_terms_are_available(): void
    {
        $shoes = Category::factory()->create();
        $hats = Category::factory()->create();
        $color = Attribute::factory()->create(['name' => 'Color']);
        $red = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Red']);
        $green = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Green']);

        $shoe = $this->purchasableProduct(['category_id' => $shoes->id]);
        $shoe->variants->first()->attributeTerms()->attach($red->id);

        $hat = $this->purchasableProduct(['category_id' => $hats->id]);
        $hat->variants->first()->attributeTerms()->attach($green->id);

        $component = Livewire::test(ProductListingPage::class)->set('category', $shoes->slug);

        $available = $component->instance()->availableTermIdsByAttribute();

        $this->assertContains($red->id, $available[$color->id]);
        $this->assertNotContains($green->id, $available[$color->id]);
    }

    public function test_reset_filters_clears_selected_attribute_filters(): void
    {
        $color = Attribute::factory()->create(['name' => 'Color']);
        $red = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Red']);
        $product = $this->purchasableProduct();
        $product->variants->first()->attributeTerms()->attach($red->id);

        Livewire::test(ProductListingPage::class)
            ->call('toggleAttributeTerm', $color->id, $red->id)
            ->assertSet('attributeFilters', [$color->id => [$red->id]])
            ->call('resetFilters')
            ->assertSet('attributeFilters', []);
    }
}
