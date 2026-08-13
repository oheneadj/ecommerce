<?php

/**
 * Covers the public storefront homepage (/).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
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

    public function test_the_homepage_shows_a_brand_that_has_a_logo_and_an_active_product(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme Gear', 'logo_path' => 'brands/acme.png']);
        $product = Product::factory()->create(['status' => ProductStatus::Active, 'brand_id' => $brand->id]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->get('/')->assertOk()->assertSee('Acme Gear');
    }

    public function test_the_homepage_never_shows_a_brand_with_no_logo(): void
    {
        $brand = Brand::factory()->create(['name' => 'Logoless Co', 'logo_path' => null]);
        $product = Product::factory()->create(['status' => ProductStatus::Active, 'brand_id' => $brand->id]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->get('/')->assertOk()->assertDontSee('Logoless Co');
    }

    public function test_the_homepage_never_shows_a_brand_with_no_active_products(): void
    {
        Brand::factory()->create(['name' => 'Idle Brand', 'logo_path' => 'brands/idle.png']);

        $this->get('/')->assertOk()->assertDontSee('Idle Brand');
    }

    public function test_the_footer_shows_the_stores_business_name_and_socials(): void
    {
        StoreSetting::current()->update([
            'business_name' => 'Acme Store',
            'facebook_url' => 'https://facebook.com/acme',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Acme Store')
            ->assertSee('https://facebook.com/acme', false);
    }

    public function test_the_whatsapp_chat_bubble_shows_when_enabled_with_a_link_set(): void
    {
        StoreSetting::current()->update([
            'whatsapp_url' => 'https://wa.me/233200000000',
            'whatsapp_chat_enabled' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Chat with us on WhatsApp');
    }

    /**
     * The wa.me link itself can legitimately still appear elsewhere on the
     * page (the footer's social icon reads `whatsapp_url` independently of
     * this toggle), so this asserts on the chat-bubble-specific aria-label
     * rather than the raw URL, which the toggle alone doesn't control.
     */
    public function test_the_whatsapp_chat_bubble_is_hidden_when_disabled(): void
    {
        StoreSetting::current()->update([
            'whatsapp_url' => 'https://wa.me/233200000000',
            'whatsapp_chat_enabled' => false,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Chat with us on WhatsApp');
    }

    public function test_the_whatsapp_chat_bubble_is_hidden_when_enabled_with_no_link_set(): void
    {
        StoreSetting::current()->update([
            'whatsapp_url' => null,
            'whatsapp_chat_enabled' => true,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Chat with us on WhatsApp');
    }
}
