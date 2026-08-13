<?php

/**
 * Regression: the storefront product-detail gallery (and every other
 * consumer of Product::images()/ProductVariant::images()) previously
 * ignored `sort_order` entirely, returning images in whatever order the
 * database happened to return them — usually insertion order — regardless
 * of how an admin had drag-reordered them.
 */

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImageOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_images_are_returned_in_sort_order_not_insertion_order(): void
    {
        $product = Product::factory()->create();
        $third = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2]);
        $first = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $product->images()->pluck('id')->all(),
        );
    }

    public function test_variant_images_are_returned_in_sort_order_not_insertion_order(): void
    {
        $variant = ProductVariant::factory()->create();
        $third = ProductImage::factory()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'sort_order' => 2]);
        $first = ProductImage::factory()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'sort_order' => 1]);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $variant->images()->pluck('id')->all(),
        );
    }

    public function test_gallery_images_for_a_variant_scoped_image_set_respect_sort_order(): void
    {
        $variant = ProductVariant::factory()->create();
        $third = ProductImage::factory()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'sort_order' => 2]);
        $first = ProductImage::factory()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'sort_order' => 1]);

        $variant->load('images');

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $variant->galleryImages()->pluck('id')->all(),
        );
    }

    public function test_gallery_images_for_a_general_product_image_set_respect_sort_order(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $third = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2]);
        $first = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);

        $product->load('images');
        $variant->load('images', 'product', 'attributeTerms');

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $variant->galleryImages()->pluck('id')->all(),
        );
    }
}
