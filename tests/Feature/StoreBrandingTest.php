<?php

/**
 * Covers store branding (business name, logo) applied to the page
 * `<title>` and favicon — shared across every storefront/auth page via
 * `partials/head.blade.php`.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_title_uses_the_business_name(): void
    {
        StoreSetting::current()->update(['business_name' => 'Acme Store']);

        $this->get('/')->assertOk()->assertSee('Acme Store', false);
    }

    public function test_the_page_title_falls_back_to_the_app_name_when_no_business_name_is_set(): void
    {
        $this->get('/')->assertOk()->assertSee(config('app.name'), false);
    }

    public function test_the_favicon_uses_the_store_logo_when_set(): void
    {
        Storage::fake('public');
        $logo = Storage::disk('public')->putFile('logos', UploadedFile::fake()->image('logo.png'));
        $this->assertIsString($logo);
        StoreSetting::current()->update(['logo_path' => $logo]);

        $this->get('/')
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($logo), false)
            ->assertDontSee('/favicon.ico', false);
    }

    public function test_the_favicon_falls_back_to_the_static_file_when_no_logo_is_set(): void
    {
        $this->get('/')->assertOk()->assertSee('/favicon.ico', false);
    }
}
