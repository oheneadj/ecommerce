<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\ArchiveProduct;
use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\DeleteProductVariant;
use App\Actions\Catalog\UpdateProduct;
use App\Enums\ProductStatus;
use App\Exceptions\ProductRequiresVariantException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_product_requires_at_least_one_variant(): void
    {
        $category = Category::factory()->create();

        $this->expectException(ProductRequiresVariantException::class);

        CreateProduct::run([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => ProductStatus::Active,
        ], []);
    }

    public function test_creating_a_product_with_variants_saves_both(): void
    {
        $category = Category::factory()->create();

        $product = CreateProduct::run([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => ProductStatus::Active,
        ], [
            ['sku' => 'SKU-1', 'price' => 1500, 'stock' => 10],
            ['sku' => 'SKU-2', 'price' => 1800, 'stock' => 5],
        ]);

        $this->assertSame('test-product', $product->slug);
        $this->assertCount(2, $product->variants);
    }

    public function test_creating_a_product_with_a_variant_low_stock_threshold_saves_it(): void
    {
        $category = Category::factory()->create();

        $product = CreateProduct::run([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => ProductStatus::Active,
        ], [
            ['sku' => 'SKU-1', 'price' => 1500, 'stock' => 10, 'low_stock_threshold' => 3],
        ]);

        $this->assertSame(3, $product->variants->first()->low_stock_threshold);
    }

    public function test_creating_a_product_with_variant_attribute_values_saves_them(): void
    {
        $category = Category::factory()->create();

        $product = CreateProduct::run([
            'category_id' => $category->id,
            'name' => 'Kente Shirt',
            'slug' => 'kente-shirt',
            'status' => ProductStatus::Active,
        ], [
            [
                'sku' => 'SHIRT-M-RED',
                'price' => 3000,
                'stock' => 10,
                'attributeValues' => [
                    ['attribute_name' => 'Size', 'value' => 'M'],
                    ['attribute_name' => 'Color', 'value' => 'Red'],
                ],
            ],
        ]);

        $variant = $product->variants->first();
        $this->assertSame(2, $variant->attributeValues->count());
        $this->assertSame(
            ['Size' => 'M', 'Color' => 'Red'],
            $variant->attributeValues->pluck('value', 'attribute_name')->all(),
        );
    }

    public function test_creating_a_draft_product_with_no_variants_succeeds(): void
    {
        $category = Category::factory()->create();

        $product = CreateProduct::run([
            'category_id' => $category->id,
            'name' => 'Work In Progress',
            'slug' => 'work-in-progress',
            'status' => ProductStatus::Draft,
        ], []);

        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertCount(0, $product->variants);
    }

    public function test_updating_a_product_to_active_with_no_variants_throws(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Draft]);

        $this->expectException(ProductRequiresVariantException::class);

        UpdateProduct::run($product, ['status' => ProductStatus::Active]);
    }

    public function test_updating_a_product_to_active_with_a_variant_succeeds(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Draft]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        UpdateProduct::run($product, ['status' => ProductStatus::Active]);

        $this->assertSame(ProductStatus::Active, $product->fresh()->status);
    }

    public function test_updating_a_draft_products_other_fields_does_not_require_a_variant(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Draft, 'name' => 'Old Name']);

        UpdateProduct::run($product, ['name' => 'New Name']);

        $this->assertSame('New Name', $product->fresh()->name);
    }

    public function test_deleting_the_last_variant_of_an_active_product_downgrades_it_to_draft(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $wasDowngraded = DeleteProductVariant::run($variant);

        $this->assertTrue($wasDowngraded);
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_deleting_one_of_several_variants_does_not_downgrade_the_product(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $keep = ProductVariant::factory()->create(['product_id' => $product->id]);
        $delete = ProductVariant::factory()->create(['product_id' => $product->id]);

        $wasDowngraded = DeleteProductVariant::run($delete);

        $this->assertFalse($wasDowngraded);
        $this->assertSame(ProductStatus::Active, $product->fresh()->status);
        $this->assertNotNull($keep->fresh());
    }

    public function test_deleting_the_last_variant_of_an_already_draft_product_does_not_error(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Draft]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $wasDowngraded = DeleteProductVariant::run($variant);

        $this->assertFalse($wasDowngraded);
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_creating_a_product_with_a_blank_attribute_row_ignores_it(): void
    {
        $category = Category::factory()->create();

        $product = CreateProduct::run([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => ProductStatus::Active,
        ], [
            [
                'sku' => 'SKU-1',
                'price' => 1500,
                'stock' => 10,
                'attributeValues' => [
                    ['attribute_name' => '', 'value' => ''],
                ],
            ],
        ]);

        $this->assertSame(0, $product->variants->first()->attributeValues->count());
    }

    public function test_archiving_a_product_stops_selling_without_deleting_or_changing_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'my-product', 'status' => ProductStatus::Active]);

        ArchiveProduct::run($product);

        $product->refresh();
        $this->assertSame(ProductStatus::Archived, $product->status);
        $this->assertSame('my-product', $product->slug);
        $this->assertNull($product->deleted_at);
    }

    public function test_deleting_a_product_soft_deletes_and_mutates_the_slug_to_free_it_for_reuse(): void
    {
        $product = Product::factory()->create(['slug' => 'my-product']);
        $id = $product->id;

        DeleteProduct::run($product);

        $this->assertSoftDeleted($product);
        $this->assertSame("my-product-deleted-{$id}", $product->fresh()->slug);
    }

    public function test_repeated_create_delete_recreate_cycles_never_collide_on_slug(): void
    {
        $category = Category::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $product = CreateProduct::run([
                'category_id' => $category->id,
                'name' => 'Repeating Product',
                'slug' => 'repeating-product',
                'status' => ProductStatus::Active,
            ], [
                ['sku' => "SKU-REPEAT-{$i}", 'price' => 1000, 'stock' => 1],
            ]);

            DeleteProduct::run($product);
        }

        $this->assertSame(3, Product::withTrashed()->where('slug', 'like', 'repeating-product-deleted-%')->count());

        // The original slug is free again for a brand new, non-deleted product.
        $final = CreateProduct::run([
            'category_id' => $category->id,
            'name' => 'Repeating Product',
            'slug' => 'repeating-product',
            'status' => ProductStatus::Active,
        ], [
            ['sku' => 'SKU-REPEAT-FINAL', 'price' => 1000, 'stock' => 1],
        ]);

        $this->assertSame('repeating-product', $final->slug);
    }

    public function test_deleting_a_variant_soft_deletes_and_mutates_the_sku_to_free_it_for_reuse(): void
    {
        $variant = ProductVariant::factory()->create(['sku' => 'MY-SKU']);
        $id = $variant->id;

        DeleteProductVariant::run($variant);

        $this->assertSoftDeleted($variant);
        $this->assertSame("MY-SKU-deleted-{$id}", $variant->fresh()->sku);
    }

    public function test_deleting_a_product_or_variant_does_not_expose_a_raw_bigint_id_via_route_key(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertSame('ulid', $variant->getRouteKeyName());
        $this->assertNotSame((string) $variant->id, $variant->getRouteKey());
    }

    public function test_product_deletion_rolls_back_slug_mutation_on_failure(): void
    {
        $product = Product::factory()->create(['slug' => 'my-product']);

        Product::deleting(function (): void {
            throw new \RuntimeException('simulated failure after slug mutation');
        });

        try {
            DeleteProduct::run($product);
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $product->refresh();
        $this->assertSame('my-product', $product->slug);
        $this->assertNull($product->deleted_at);
    }

    public function test_product_variant_deletion_rolls_back_sku_mutation_on_failure(): void
    {
        $variant = ProductVariant::factory()->create(['sku' => 'MY-SKU']);

        ProductVariant::deleting(function (): void {
            throw new \RuntimeException('simulated failure after sku mutation');
        });

        try {
            DeleteProductVariant::run($variant);
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $variant->refresh();
        $this->assertSame('MY-SKU', $variant->sku);
        $this->assertNull($variant->deleted_at);
    }

    public function test_failed_product_creation_rolls_back_the_product_and_its_variants(): void
    {
        $category = Category::factory()->create();

        ProductVariant::creating(function (): void {
            throw new \RuntimeException('simulated failure creating a variant');
        });

        try {
            CreateProduct::run([
                'category_id' => $category->id,
                'name' => 'Rollback Product',
                'slug' => 'rollback-product',
                'status' => ProductStatus::Active,
            ], [
                ['sku' => 'SKU-ROLLBACK', 'price' => 1000, 'stock' => 1],
            ]);
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, Product::query()->where('slug', 'rollback-product')->count());
    }
}
