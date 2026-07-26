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
}
