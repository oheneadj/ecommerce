<?php

/**
 * Covers the SEO/local-SEO additions from the full-app audit: sitemap.xml,
 * robots.txt, canonical tags, noindex on private pages, Product/
 * BreadcrumbList/LocalBusiness structured data, and Google Analytics.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StaticPage;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_the_homepage_active_products_and_published_pages(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);
        $draftProduct = Product::factory()->create(['status' => ProductStatus::Draft]);
        $page = StaticPage::factory()->create(['is_published' => true]);
        $unpublishedPage = StaticPage::factory()->create(['is_published' => false]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $response->assertSee(route('products.show', $product->slug), false);
        $response->assertSee(route('pages.show', $page->slug), false);
        $response->assertDontSee(route('products.show', $draftProduct->slug), false);
        $response->assertDontSee(route('pages.show', $unpublishedPage->slug), false);
    }

    public function test_robots_txt_references_the_sitemap_and_disallows_private_pages(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Sitemap: '.url('/sitemap.xml'));
        $response->assertSee('Disallow: /cart');
        $response->assertSee('Disallow: /checkout');
        $response->assertSee('Disallow: /account');
    }

    public function test_the_cart_page_carries_a_noindex_tag(): void
    {
        $this->get('/cart')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_the_homepage_does_not_carry_a_noindex_tag(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('name="robots"', false);
    }

    public function test_a_product_page_has_a_canonical_tag_stripped_of_query_parameters(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $response = $this->get("/products/{$product->slug}?utm_source=test");

        $response->assertOk();
        $response->assertSee('rel="canonical" href="'.route('products.show', $product->slug).'"', false);
    }

    public function test_a_product_page_renders_product_and_breadcrumb_structured_data(): void
    {
        $product = Product::factory()->create(['name' => 'Blue Suede Sneakers', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 15000, 'stock' => 5]);

        $response = $this->get("/products/{$product->slug}");

        $response->assertOk();
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('"name":"Blue Suede Sneakers"', false);
        $response->assertSee('"price":"150.00"', false);
        $response->assertSee('"availability":"https:\/\/schema.org\/InStock"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_local_business_structured_data_only_renders_once_a_structured_address_is_set(): void
    {
        $this->get('/')->assertOk()->assertDontSee('"@type":"LocalBusiness"', false);

        StoreSetting::current()->update([
            'address_street' => '12 Independence Ave',
            'address_city' => 'Accra',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('"@type":"LocalBusiness"', false)
            ->assertSee('"streetAddress":"12 Independence Ave"', false);
    }

    public function test_google_analytics_only_loads_once_a_measurement_id_is_configured(): void
    {
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com/gtag/js', false);

        StoreSetting::current()->update(['ga_measurement_id' => 'G-ABC123XYZ']);

        $this->get('/')
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-ABC123XYZ', false);
    }
}
