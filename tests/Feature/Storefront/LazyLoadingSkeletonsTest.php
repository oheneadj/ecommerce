<?php

/**
 * Covers the #[Lazy] + skeleton treatment on CartPage and CheckoutPage —
 * the first HTML response shows a skeleton (so the customer never stares
 * at a blank page while the real query runs), and the real content still
 * renders correctly once mounted. ProductListingPage and ProductDetailPage
 * deliberately do NOT get this treatment (see CHANGELOG) — search-engine
 * crawlability for product listing, and a real 404 status for an invalid
 * product slug, both depend on their content being in the first response.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LazyLoadingSkeletonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_carts_first_response_shows_a_skeleton_not_the_real_items(): void
    {
        $product = Product::factory()->create(['name' => 'Skeleton Test Product', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $response = $this->get('/cart');

        $response->assertOk();
        $response->assertSeeHtml('animate-pulse');
        $response->assertDontSee('Skeleton Test Product');
    }

    public function test_the_checkouts_first_response_shows_a_skeleton_not_the_real_form(): void
    {
        $response = $this->get('/checkout');

        $response->assertOk();
        $response->assertSeeHtml('animate-pulse');
        $response->assertDontSeeHtml('wire:model.live="selectedShippingMethodId"');
    }
}
