<?php

/**
 * Bug-hunt regression: several Filament resources' `DeleteBulkAction` had
 * no `authorizeIndividualRecords('delete')` call, so Filament fell back to
 * checking a single batch-wide `deleteAny` ability — absent from every
 * policy in this app, which defaults to allow when not in strict mode.
 * Since several of these resources grant Store Keeper `viewAny` (list
 * access) while restricting `delete` to Admin/Super Admin, Store Keeper
 * could reach the list and bulk-delete records the single-record delete
 * path correctly denied them.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkDeleteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function storeKeeper(): User
    {
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::StoreKeeper->value);

        return $user;
    }

    public function test_store_keeper_cannot_bulk_delete_products(): void
    {
        $this->actingAs($this->storeKeeper());

        $product = Product::factory()->create();

        Livewire::test(ListProducts::class)->callTableBulkAction('delete', [$product]);

        $this->assertModelExists($product);
    }

    public function test_store_keeper_cannot_bulk_delete_categories(): void
    {
        $this->actingAs($this->storeKeeper());

        $category = Category::factory()->create();

        Livewire::test(ListCategories::class)->callTableBulkAction('delete', [$category]);

        $this->assertModelExists($category);
    }

    public function test_store_keeper_cannot_bulk_delete_brands(): void
    {
        $this->actingAs($this->storeKeeper());

        $brand = Brand::factory()->create();

        Livewire::test(ListBrands::class)->callTableBulkAction('delete', [$brand]);

        $this->assertModelExists($brand);
    }

    public function test_store_keeper_cannot_bulk_delete_product_images(): void
    {
        $this->actingAs($this->storeKeeper());

        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableBulkAction('delete', [$image]);

        $this->assertModelExists($image);
    }
}
