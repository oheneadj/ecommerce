<?php

/**
 * Covers the Brand logo upload — converted to WebP on save, and rejected
 * above the configured `media.max_upload_size_kb` limit.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrandFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_a_brand_logo_upload_is_converted_to_webp(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Livewire::test(CreateBrand::class)
            ->fillForm([
                'name' => 'Acme',
                'slug' => 'acme',
                'logo_path' => UploadedFile::fake()->image('logo.jpg'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $logoPath = Brand::query()->where('slug', 'acme')->sole()->logo_path;
        $this->assertStringEndsWith('.webp', $logoPath);
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_a_brand_logo_upload_over_the_configured_size_limit_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        config(['media.max_upload_size_kb' => 100]);

        // FileUpload::maxSize() registers its rule via a Closure rather than a
        // plain "max" string rule, so Livewire's failed-rule matching can't
        // key off the rule name — assert on the key alone instead.
        Livewire::test(CreateBrand::class)
            ->fillForm([
                'name' => 'Acme',
                'slug' => 'acme',
                'logo_path' => UploadedFile::fake()->image('logo.jpg')->size(200),
            ])
            ->call('create')
            ->assertHasFormErrors(['logo_path']);
    }
}
