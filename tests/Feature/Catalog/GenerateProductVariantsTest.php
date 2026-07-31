<?php

/**
 * Covers bulk-generating every combination across a set of global attribute
 * terms (e.g. Size × Color) as separate variants, instead of adding them
 * by hand.
 */

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\GenerateProductVariants;
use App\Models\Attribute;
use App\Models\AttributeTerm;
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
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $sizeTerms = AttributeTerm::factory()->count(3)->create(['attribute_id' => $size->id]);
        $colorTerms = AttributeTerm::factory()->count(3)->create(['attribute_id' => $color->id]);

        $created = GenerateProductVariants::run(
            $product,
            [$sizeTerms->pluck('id')->all(), $colorTerms->pluck('id')->all()],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'SHIRT',
        );

        $this->assertSame(9, $created->count());
        $this->assertSame(9, $product->variants()->count());
    }

    public function test_each_generated_variant_carries_the_matching_attribute_terms(): void
    {
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $xl = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'XL']);
        $white = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'White']);

        GenerateProductVariants::run(
            $product,
            [[$xl->id], [$white->id]],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'SHIRT',
        );

        $variant = $product->variants()->sole();
        $this->assertSame(['XL', 'White'], $variant->attributeTerms()->pluck('value')->all());
    }

    public function test_it_skips_a_combination_that_already_exists_on_the_product(): void
    {
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $m = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'M']);
        $l = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'L']);
        $blue = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Blue']);

        $existing = ProductVariant::factory()->create(['product_id' => $product->id]);
        $existing->attributeTerms()->attach([$m->id, $blue->id]);

        $created = GenerateProductVariants::run(
            $product,
            [[$m->id, $l->id], [$blue->id]],
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
        $size = Attribute::factory()->create(['name' => 'Size']);
        $extraLarge = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'Extra Large']);

        GenerateProductVariants::run(
            $product,
            [[$extraLarge->id]],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'shirt',
        );

        $this->assertSame('SHIRT-EXTRA-LARGE', $product->variants()->sole()->sku);
    }
}
