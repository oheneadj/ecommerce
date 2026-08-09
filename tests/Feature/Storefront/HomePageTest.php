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

    public function test_the_homepage_never_shows_a_draft_product(): void
    {
        $product = Product::factory()->create(['name' => 'Draft Item', 'status' => ProductStatus::Draft]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->get('/')->assertOk()->assertDontSee('Draft Item');
    }
}
