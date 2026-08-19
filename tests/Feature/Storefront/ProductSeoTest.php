<?php

/**
 * Covers the product detail page's <title>/Open Graph/Twitter Card tags.
 * Previously the outer page wrapper set a hardcoded, generic <title> of
 * "Product" and no Open Graph tags existed anywhere in the app at all —
 * with no og:image, a link-preview crawler (WhatsApp, Facebook, iMessage,
 * etc.) fell back to whatever image it could find on the page, which in
 * practice meant the store's header logo instead of the product photo.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_title_is_the_products_actual_name_not_a_generic_placeholder(): void
    {
        $product = Product::factory()->create(['name' => 'Blue Suede Sneakers', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('<title>', false)
            ->assertSee('Blue Suede Sneakers', false);
    }

    public function test_the_products_primary_image_is_used_as_the_og_image_not_the_store_logo(): void
    {
        Storage::fake('public');
        StoreSetting::current()->update(['logo_path' => 'branding/logo.png']);

        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'product-images/other.webp', 'is_primary' => false]);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'product-images/hero.webp', 'is_primary' => true]);

        $response = $this->get("/products/{$product->slug}");

        $response->assertOk();
        $response->assertSee('og:image" content="'.Storage::disk('public')->url('product-images/hero.webp'), false);
        $response->assertDontSee('og:image" content="'.Storage::disk('public')->url('branding/logo.png'), false);
    }

    public function test_falls_back_to_the_store_logo_when_the_product_has_no_images(): void
    {
        Storage::fake('public');
        StoreSetting::current()->update(['logo_path' => 'branding/logo.png']);

        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('og:image" content="'.Storage::disk('public')->url('branding/logo.png'), false);
    }

    public function test_uses_the_products_meta_description_when_set(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::Active,
            'meta_description' => 'The comfiest sneakers you will ever own.',
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('og:description" content="The comfiest sneakers you will ever own.', false);
    }

    public function test_og_type_is_product(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('og:type" content="product"', false);
    }

    public function test_a_nonexistent_product_slug_still_renders_a_clean_404_not_a_crash(): void
    {
        $this->get('/products/does-not-exist')->assertNotFound();
    }

    public function test_the_homepage_has_a_default_og_image_from_the_store_logo(): void
    {
        Storage::fake('public');
        StoreSetting::current()->update(['logo_path' => 'branding/logo.png']);

        $this->get('/')
            ->assertOk()
            ->assertSee('og:image" content="'.Storage::disk('public')->url('branding/logo.png'), false);
    }
}
