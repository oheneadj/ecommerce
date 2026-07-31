<?php

/**
 * Covers the model-level relations backing the global attribute catalog —
 * Product <-> Attribute and ProductVariant <-> AttributeTerm.
 */

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalAttributeRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_have_global_attributes_enabled(): void
    {
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);

        $product->attributes()->attach([$size->id, $color->id]);

        $this->assertSame(['Size', 'Color'], $product->attributes->pluck('name')->all());
        $this->assertTrue($size->products->contains($product));
    }

    public function test_an_attribute_has_many_terms(): void
    {
        $size = Attribute::factory()->create();
        AttributeTerm::factory()->count(3)->create(['attribute_id' => $size->id]);

        $this->assertCount(3, $size->terms);
    }

    public function test_a_variant_can_have_attribute_terms_selected(): void
    {
        $variant = ProductVariant::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $large = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'Large']);

        $variant->attributeTerms()->attach($large->id);

        $this->assertSame(['Large'], $variant->attributeTerms->pluck('value')->all());
        $this->assertTrue($large->productVariants->contains($variant));
    }

    public function test_deleting_an_attribute_cascades_to_its_terms(): void
    {
        $size = Attribute::factory()->create();
        $term = AttributeTerm::factory()->create(['attribute_id' => $size->id]);

        $size->delete();

        $this->assertDatabaseMissing('attribute_terms', ['id' => $term->id]);
    }
}
