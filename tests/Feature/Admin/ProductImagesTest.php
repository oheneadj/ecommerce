<?php

/**
 * Covers uploading product-level and variant-scoped images from the Filament
 * admin panel, and that removing an image cleans up its stored file.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function storeKeeper(): User
    {
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::StoreKeeper->value);

        return $user;
    }

    public function test_uploading_a_general_product_image_leaves_the_variant_scope_blank(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('front.jpg'),
                'sort_order' => 0,
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $image = $product->images()->sole();
        $this->assertNull($image->product_variant_id);
        $this->assertTrue($image->is_primary);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_uploading_a_variant_scoped_image_records_the_variant(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('red-variant.jpg'),
                'product_variant_id' => $variant->id,
                'sort_order' => 1,
                'is_primary' => false,
            ])
            ->assertHasNoTableActionErrors();

        $image = $product->images()->sole();
        $this->assertSame($variant->id, $image->product_variant_id);
    }

    public function test_an_upload_over_the_configured_size_limit_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        config(['media.max_upload_size_kb' => 100]);

        $product = Product::factory()->create();

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('too-big.jpg')->size(200),
                'sort_order' => 0,
                'is_primary' => true,
            ])
            // FileUpload::maxSize() registers a Closure rule rather than a
            // plain "max" string, so assert on the key alone.
            ->assertHasTableActionErrors(['path']);
    }

    public function test_deleting_an_image_removes_the_stored_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $path = 'product-images/existing.jpg';
        Storage::disk('public')->put($path, 'fake-image-contents');
        $image = ProductImage::factory()->create(['product_id' => $product->id, 'path' => $path]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('delete', $image)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_store_keeper_cannot_delete_a_product_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->storeKeeper());

        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertTableActionHidden('delete', $image);
    }

    public function test_adding_an_image_from_a_variant_row_scopes_it_to_that_variant(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variant, data: [
                'path' => UploadedFile::fake()->image('variant.jpg'),
                'sort_order' => 0,
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $image = $product->images()->sole();
        $this->assertSame($variant->id, $image->product_variant_id);
        $this->assertTrue($image->is_primary);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_store_keeper_can_also_add_an_image_from_a_variant_row(): void
    {
        Storage::fake('public');
        $this->actingAs($this->storeKeeper());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variant, data: [
                'path' => UploadedFile::fake()->image('variant.jpg'),
                'sort_order' => 0,
                'is_primary' => false,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($variant->id, $product->images()->sole()->product_variant_id);
    }

    public function test_marking_a_new_general_image_primary_unmarks_the_previous_primary_general_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $existingPrimary = ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'is_primary' => true,
        ]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('new-front.jpg'),
                'sort_order' => 0,
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertFalse($existingPrimary->fresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_marking_a_variant_image_primary_does_not_affect_a_different_variants_primary_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variantA = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variantB = ProductVariant::factory()->create(['product_id' => $product->id]);

        $primaryForA = ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variantA->id,
            'is_primary' => true,
        ]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variantB, data: [
                'path' => UploadedFile::fake()->image('variant-b.jpg'),
                'sort_order' => 0,
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($primaryForA->fresh()->is_primary);
    }
}
