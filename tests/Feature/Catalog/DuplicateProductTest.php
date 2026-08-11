<?php

/**
 * Covers App\Actions\Catalog\DuplicateProduct — copying a product (fields,
 * attributes, variants, images) as a new, independent Draft product.
 */

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\DuplicateProduct;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VariantStatus;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DuplicateProductTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_the_duplicate_row_action_creates_a_draft_copy(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create(['name' => 'Kente Shirt', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(ListProducts::class)
            ->callTableAction('duplicate', $product)
            ->assertHasNoTableActionErrors();

        $copy = Product::query()->where('name', 'Kente Shirt (Copy)')->sole();
        $this->assertSame(ProductStatus::Draft, $copy->status);
    }

    public function test_the_copy_gets_a_new_name_slug_and_is_always_a_draft(): void
    {
        $product = Product::factory()->create(['name' => 'Kente Shirt', 'status' => ProductStatus::Active]);

        $copy = DuplicateProduct::run($product);

        $this->assertSame('Kente Shirt (Copy)', $copy->name);
        $this->assertNotSame($product->slug, $copy->slug);
        $this->assertSame(ProductStatus::Draft, $copy->status);
        $this->assertSame($product->category_id, $copy->category_id);
        $this->assertSame($product->brand_id, $copy->brand_id);
    }

    public function test_a_draft_original_still_produces_a_draft_copy(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Draft]);

        $copy = DuplicateProduct::run($product);

        $this->assertSame(ProductStatus::Draft, $copy->status);
    }

    public function test_enabled_global_attributes_are_copied(): void
    {
        $product = Product::factory()->create();
        $color = Attribute::factory()->create();
        $size = Attribute::factory()->create();
        $product->attributes()->attach([$color->id, $size->id]);

        $copy = DuplicateProduct::run($product);

        $this->assertSame([$color->id, $size->id], $copy->attributes()->pluck('attributes.id')->sort()->values()->all());
    }

    public function test_every_variant_is_copied_with_a_fresh_sku(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'ORIG-SKU',
            'price' => 1500,
            'stock' => 7,
            'low_stock_threshold' => 3,
            'status' => VariantStatus::Active,
        ]);

        $copy = DuplicateProduct::run($product);

        $copyVariant = $copy->variants()->sole();
        $this->assertNotSame($variant->sku, $copyVariant->sku);
        $this->assertNotSame($variant->ulid, $copyVariant->ulid);
        $this->assertSame(1500, $copyVariant->price);
        $this->assertSame(7, $copyVariant->stock);
        $this->assertSame(3, $copyVariant->low_stock_threshold);
        $this->assertSame(VariantStatus::Active, $copyVariant->status);
    }

    public function test_a_variants_custom_attribute_values_are_copied(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->attributeValues()->create(['attribute_name' => 'Size', 'value' => 'Large']);

        $copy = DuplicateProduct::run($product);

        $copyVariant = $copy->variants()->sole();
        $this->assertSame(['Size' => 'Large'], $copyVariant->attributeValues()->pluck('value', 'attribute_name')->all());
    }

    public function test_a_variants_global_attribute_term_links_are_copied(): void
    {
        $product = Product::factory()->create();
        $color = Attribute::factory()->create();
        $red = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Red']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->attributeTerms()->attach($red->id);

        $copy = DuplicateProduct::run($product);

        $copyVariant = $copy->variants()->sole();
        $this->assertSame([$red->id], $copyVariant->attributeTerms()->pluck('attribute_terms.id')->all());
    }

    public function test_product_and_variant_images_are_physically_copied_not_shared(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $productImagePath = Storage::disk('public')->putFile('product-images', UploadedFile::fake()->image('product.jpg'));
        $this->assertIsString($productImagePath);
        $product->images()->create(['path' => $productImagePath, 'sort_order' => 0, 'is_primary' => true]);

        $variantImagePath = Storage::disk('public')->putFile('product-images', UploadedFile::fake()->image('variant.jpg'));
        $this->assertIsString($variantImagePath);
        $variant->images()->create(['product_id' => $product->id, 'path' => $variantImagePath, 'sort_order' => 0, 'is_primary' => true]);

        $copy = DuplicateProduct::run($product);

        $copyProductImage = $copy->images()->whereNull('product_variant_id')->sole();
        $copyVariant = $copy->variants()->sole();
        $copyVariantImage = $copyVariant->images()->sole();

        // New rows, new physical files — not the same path as the originals.
        $this->assertNotSame($productImagePath, $copyProductImage->path);
        $this->assertNotSame($variantImagePath, $copyVariantImage->path);
        Storage::disk('public')->assertExists($copyProductImage->path);
        Storage::disk('public')->assertExists($copyVariantImage->path);

        // The regression this was built to prevent: deleting the
        // ORIGINAL's image (which deletes its underlying file via
        // ProductImageObserver) must never take the copy's file with it.
        $originalImage = $product->images()->whereNull('product_variant_id')->sole();
        $originalImage->delete();

        Storage::disk('public')->assertMissing($productImagePath);
        Storage::disk('public')->assertExists($copyProductImage->path);
    }

    public function test_reviews_and_stock_history_are_never_copied(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);
        Review::factory()->create(['product_id' => $product->id]);
        StockMovement::factory()->create(['product_variant_id' => $variant->id]);

        $copy = DuplicateProduct::run($product);

        $this->assertSame(0, $copy->reviews()->count());
        $copyVariant = $copy->variants()->sole();
        $this->assertSame(0, $copyVariant->stockMovements()->count());
    }
}
