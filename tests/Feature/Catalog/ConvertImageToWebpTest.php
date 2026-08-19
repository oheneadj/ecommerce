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

    /**
     * A small file that decodes to an enormous resolution ("decompression
     * bomb") used to reach Intervention's decode() step regardless of its
     * tiny compressed size — GD allocates a full bitmap at decode time,
     * which can exhaust memory_limit on the synchronous upload request.
     * Hand-crafts a PNG with a valid header declaring a huge resolution
     * but no real pixel data — getimagesize() only reads the header (safe,
     * no decode), which is exactly what this test needs: a file that would
     * be dangerous to actually decode is rejected before that ever happens.
     */
    private function oversizedDimensionPng(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdrData = pack('N2C5', $width, $height, 8, 6, 0, 0, 0);
        $ihdrChunk = pack('N', strlen($ihdrData)).'IHDR'.$ihdrData.pack('N', 0);

        return $signature.$ihdrChunk;
    }

    public function test_an_image_exceeding_the_max_pixel_count_is_rejected_before_decoding(): void
    {
        Storage::fake('public');
        config(['media.max_image_pixels' => 1000]);

        $path = 'product-images/bomb.png';
        Storage::disk('public')->put($path, $this->oversizedDimensionPng(50000, 50000));

        $result = ConvertImageToWebp::run('public', $path);

        $this->assertSame($path, $result);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_image_within_the_max_pixel_count_is_still_converted(): void
    {
        Storage::fake('public');

        $original = Storage::disk('public')->putFile('product-images', UploadedFile::fake()->image('photo.jpg', 100, 100));

        $newPath = ConvertImageToWebp::run('public', $original);

        $this->assertStringEndsWith('.webp', $newPath);
        Storage::disk('public')->assertExists($newPath);
    }

    /**
     * A file with a genuine PNG signature (passes the MIME sniff) but
     * corrupt beyond that used to reach Intervention's own decode()
     * uncaught — Laravel's Image component doesn't wrap that call, and
     * this action previously only caught its own Illuminate\Image\
     * ImageException, not Intervention's exception hierarchy.
     */
    public function test_a_corrupt_but_mime_valid_image_never_throws_uncaught(): void
    {
        Storage::fake('public');

        $path = 'product-images/corrupt.png';
        Storage::disk('public')->put($path, "\x89PNG\r\n\x1a\n".'not actually a valid PNG body');

        $result = ConvertImageToWebp::run('public', $path);

        $this->assertIsString($result);
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
                'images' => [UploadedFile::fake()->image('front.jpg')],
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
                'images' => [UploadedFile::fake()->image('variant.png')],
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
