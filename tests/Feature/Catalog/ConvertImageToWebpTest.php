<?php

/**
 * Covers converting an uploaded image to WebP on the way to disk, and that
 * it's actually wired into every image FileUpload field in the catalog.
 */

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Enums\UserRole;
use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConvertImageToWebpTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_a_non_webp_file_is_converted_and_the_original_is_deleted(): void
    {
        Storage::fake('public');

        $original = Storage::disk('public')->putFile('product-images', UploadedFile::fake()->image('photo.jpg'));

        $newPath = ConvertImageToWebp::run('public', $original);

        $this->assertStringEndsWith('.webp', $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($original);
    }

    public function test_a_file_already_webp_is_left_untouched(): void
    {
        Storage::fake('public');

        $path = 'product-images/already.webp';
        Storage::disk('public')->put($path, 'not-real-bytes-but-extension-check-short-circuits-first');

        $result = ConvertImageToWebp::run('public', $path);

        $this->assertSame($path, $result);
        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_a_product_image_converts_it_to_webp(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('front.jpg'),
                'sort_order' => 0,
                'is_primary' => false,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertStringEndsWith('.webp', $product->images()->sole()->path);
    }

    public function test_adding_an_image_from_a_variant_row_converts_it_to_webp(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variant, data: [
                'path' => UploadedFile::fake()->image('variant.png'),
                'sort_order' => 0,
                'is_primary' => false,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertStringEndsWith('.webp', $product->images()->sole()->path);
    }

    public function test_uploading_a_brand_logo_converts_it_to_webp(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Livewire::test(CreateBrand::class)
            ->fillForm([
                'name' => 'Volta Electronics',
                'slug' => 'volta-electronics',
                'logo_path' => UploadedFile::fake()->image('logo.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $brand = Brand::query()->sole();
        $this->assertStringEndsWith('.webp', $brand->logo_path);
    }
}
