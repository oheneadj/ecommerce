<?php

/**
 * Covers bulk-generating every combination across a set of global attribute
 * terms (e.g. Size × Color) as separate variants, instead of adding them
 * by hand.
 */

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\GenerateProductVariants;
use App\Exceptions\DuplicateSkuException;
use App\Exceptions\ProductVariantLimitExceededException;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: sku is globally unique (not scoped per product), so a
     * prefix/term combo colliding with a live variant on ANY product used
     * to throw a raw QueryException mid-transaction instead of a friendly,
     * actionable message.
     */
    public function test_a_colliding_sku_on_another_product_is_rejected_with_a_friendly_message(): void
    {
        $otherProduct = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $otherProduct->id, 'sku' => 'SHIRT-RED']);

        $product = Product::factory()->create();
        $color = Attribute::factory()->create(['name' => 'Color']);
        $red = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Red']);

        $this->expectException(DuplicateSkuException::class);

        GenerateProductVariants::run(
            $product,
            [[$red->id]],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'SHIRT',
        );
    }

    /**
     * Regression: without freeing the SKU on soft-delete, a discontinued
     * variant permanently blocked its own SKU from ever being reused —
     * even on a brand-new, unrelated variant.
     */
    public function test_a_soft_deleted_variants_sku_is_freed_for_reuse(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SHIRT-RED']);
        $variant->delete();

        $newVariant = ProductVariant::factory()->create(['sku' => 'SHIRT-RED']);

        $this->assertModelExists($newVariant);
        $this->assertStringContainsString('SHIRT-RED-deleted-', ProductVariant::withTrashed()->findOrFail($variant->id)->sku);
    }

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

    public function test_generating_past_the_configured_variant_limit_is_rejected(): void
    {
        config(['media.product_max_variants' => 5]);
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $sizeTerms = AttributeTerm::factory()->count(3)->create(['attribute_id' => $size->id]);
        $colorTerms = AttributeTerm::factory()->count(3)->create(['attribute_id' => $color->id]);

        $this->expectException(ProductVariantLimitExceededException::class);

        GenerateProductVariants::run(
            $product,
            [$sizeTerms->pluck('id')->all(), $colorTerms->pluck('id')->all()],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'SHIRT',
        );
    }

    public function test_a_rejected_over_limit_generation_creates_no_variants_at_all(): void
    {
        config(['media.product_max_variants' => 5]);
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $sizeTerms = AttributeTerm::factory()->count(3)->create(['attribute_id' => $size->id]);
        $colorTerms = AttributeTerm::factory()->count(3)->create(['attribute_id' => $color->id]);

        try {
            GenerateProductVariants::run(
                $product,
                [$sizeTerms->pluck('id')->all(), $colorTerms->pluck('id')->all()],
                defaultPrice: 3000,
                defaultStock: 10,
                skuPrefix: 'SHIRT',
            );
        } catch (ProductVariantLimitExceededException) {
            // expected
        }

        $this->assertSame(0, $product->variants()->count());
    }

    public function test_combinations_already_covered_by_existing_variants_dont_count_against_the_limit(): void
    {
        config(['media.product_max_variants' => 2]);
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $m = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'M']);
        $l = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'L']);

        $existing = ProductVariant::factory()->create(['product_id' => $product->id]);
        $existing->attributeTerms()->attach($m->id);

        // Only "L" is genuinely new — re-requesting "M" is a no-op skip,
        // so this must not count as 3 combinations against a limit of 2.
        $created = GenerateProductVariants::run(
            $product,
            [[$m->id, $l->id]],
            defaultPrice: 3000,
            defaultStock: 10,
            skuPrefix: 'SHIRT',
        );

        $this->assertSame(1, $created->count());
    }
}
