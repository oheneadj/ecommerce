<?php

/**
 * Covers bulk-generating every combination across a set of attributes
 * (e.g. Size × Color) as separate variants, instead of adding them by hand.
 */

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\GenerateProductVariants;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_variant_per_combination(): void
    {
        $product = Product::factory()->create();

        $created = GenerateProductVariants::run(
            $product,
            ['Size' => ['L', 'M', 'XL'], 'Color' => ['White', 'Blue', 'Black']],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'SHIRT',
        );

        $this->assertSame(9, $created->count());
        $this->assertSame(9, $product->variants()->count());
    }

    public function test_each_generated_variant_carries_the_matching_attribute_values(): void
    {
        $product = Product::factory()->create();

        GenerateProductVariants::run(
            $product,
            ['Size' => ['XL'], 'Color' => ['White']],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'SHIRT',
        );

        $variant = $product->variants()->sole();
        $this->assertSame(
            ['Size' => 'XL', 'Color' => 'White'],
            $variant->attributeValues()->pluck('value', 'attribute_name')->all(),
        );
    }

    public function test_it_skips_a_combination_that_already_exists_on_the_product(): void
    {
        $product = Product::factory()->create();
        $existing = ProductVariant::factory()->create(['product_id' => $product->id]);
        $existing->attributeValues()->create(['attribute_name' => 'Size', 'value' => 'M']);
        $existing->attributeValues()->create(['attribute_name' => 'Color', 'value' => 'Blue']);

        $created = GenerateProductVariants::run(
            $product,
            ['Size' => ['M', 'L'], 'Color' => ['Blue']],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'SHIRT',
        );

        // Only "L / Blue" is new — "M / Blue" already existed.
        $this->assertSame(1, $created->count());
        $this->assertSame(2, $product->variants()->count());
    }

    public function test_generated_skus_are_prefixed_and_slugged(): void
    {
        $product = Product::factory()->create();

        GenerateProductVariants::run(
            $product,
            ['Size' => ['Extra Large']],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'shirt',
        );

        $this->assertSame('SHIRT-EXTRA-LARGE', $product->variants()->sole()->sku);
    }
}
