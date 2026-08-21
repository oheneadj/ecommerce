<?php

/**
 * Covers AttributeUsageSummary — the block message shown when deleting an
 * attribute still in use, naming the specific products/variants rather
 * than just a count.
 */

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\AttributeUsageSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeUsageSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_when_unused(): void
    {
        $attribute = Attribute::factory()->create();

        $this->assertNull(AttributeUsageSummary::forBlockedDelete(collect([$attribute])));
    }

    public function test_it_names_the_assigned_product(): void
    {
        $attribute = Attribute::factory()->create();
        $product = Product::factory()->create(['name' => 'Classic Tee']);
        $attribute->products()->attach($product);

        $message = AttributeUsageSummary::forBlockedDelete(collect([$attribute]));

        $this->assertSame('This attribute is still assigned on products: Classic Tee. Remove it from those first.', $message);
    }

    public function test_it_names_the_assigned_products_and_variants_together(): void
    {
        $attribute = Attribute::factory()->create();
        $product = Product::factory()->create(['name' => 'Classic Tee']);
        $attribute->products()->attach($product);
        $term = $attribute->terms()->create(['value' => 'Red', 'slug' => 'red']);
        $variant = ProductVariant::factory()->create(['sku' => 'TEE-RED-M']);
        $variant->attributeTerms()->attach($term);

        $message = AttributeUsageSummary::forBlockedDelete(collect([$attribute]));

        $this->assertSame('This attribute is still assigned on products: Classic Tee; variants: TEE-RED-M. Remove it from those first.', $message);
    }

    public function test_it_truncates_a_long_list_and_says_how_many_more(): void
    {
        $attribute = Attribute::factory()->create();
        $products = Product::factory()->count(7)->create();
        $attribute->products()->attach($products);

        $message = AttributeUsageSummary::forBlockedDelete(collect([$attribute]));

        $this->assertStringContainsString('and 2 more', $message);
    }

    public function test_it_uses_plural_phrasing_for_multiple_attributes(): void
    {
        $productA = Product::factory()->create(['name' => 'Tee']);
        $productB = Product::factory()->create(['name' => 'Hoodie']);
        $attributeA = Attribute::factory()->create();
        $attributeB = Attribute::factory()->create();
        $attributeA->products()->attach($productA);
        $attributeB->products()->attach($productB);

        $message = AttributeUsageSummary::forBlockedDelete(collect([$attributeA, $attributeB]));

        $this->assertStringStartsWith('These attributes are', $message);
        $this->assertStringContainsString('Tee', $message);
        $this->assertStringContainsString('Hoodie', $message);
    }
}
