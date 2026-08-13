<?php

/**
 * Covers the Store Settings page — Super-Admin-only, and the branding/tax
 * fields added for Epic E13 (business name, logo, colors, tagline, contact
 * details, tax rate) alongside the pre-existing operational settings.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SmsProvider;
use App\Enums\UserRole;
use App\Filament\Pages\ManageStoreSettings;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageStoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_super_admin_can_view_the_page(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageStoreSettings::class)->assertSuccessful();
    }

    public function test_admin_cannot_access_the_page(): void
    {
        $this->actingAs($this->admin());

        $this->assertFalse(ManageStoreSettings::canAccess());
    }

    public function test_super_admin_can_update_branding_and_tax_fields(): void
    {
        Storage::fake('public');
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'business_name' => 'Acme Store',
                'tagline' => 'Great stuff, fast.',
                'primary_color' => '#111827',
                'secondary_color' => '#6366f1',
                'logo_path' => UploadedFile::fake()->image('logo.jpg'),
                'contact_email' => 'hello@acme.test',
                'contact_phone' => '+233200000000',
                'contact_address' => 'Accra, Ghana',
                'tax_rate' => 15,
                'stock_reservation_minutes' => 15,
                'low_stock_threshold' => 5,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = StoreSetting::current();
        $this->assertSame('Acme Store', $settings->business_name);
        $this->assertSame(15, $settings->tax_rate);
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_super_admin_can_update_social_links(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'facebook_url' => 'https://facebook.com/acme',
                'instagram_url' => 'https://instagram.com/acme',
                'x_url' => 'https://x.com/acme',
                'tiktok_url' => 'https://tiktok.com/@acme',
                'whatsapp_url' => 'https://wa.me/233200000000',
                'tax_rate' => 15,
                'stock_reservation_minutes' => 15,
                'low_stock_threshold' => 5,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = StoreSetting::current();
        $this->assertSame('https://facebook.com/acme', $settings->facebook_url);
        $this->assertSame('https://wa.me/233200000000', $settings->whatsapp_url);
        $this->assertSame(['facebook', 'instagram', 'x', 'tiktok', 'whatsapp'], array_keys($settings->socialLinks()));
    }

    public function test_super_admin_can_toggle_the_whatsapp_chat_bubble(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'whatsapp_url' => 'https://wa.me/233200000000',
                'whatsapp_chat_enabled' => true,
                'tax_rate' => 15,
                'stock_reservation_minutes' => 15,
                'low_stock_threshold' => 5,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(StoreSetting::current()->whatsapp_chat_enabled);
    }

    public function test_invalid_social_url_is_rejected(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'facebook_url' => 'not-a-url',
                'tax_rate' => 15,
                'stock_reservation_minutes' => 15,
                'low_stock_threshold' => 5,
            ])
            ->call('save')
            ->assertHasFormErrors(['facebook_url' => 'url']);
    }

    public function test_super_admin_can_set_the_active_sms_provider(): void
    {
        config([
            'sms.providers.giantsms.api_token' => 'test-token',
            'sms.providers.giantsms.sender_id' => 'TestSender',
        ]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'active_sms_provider' => 'giantsms',
                'tax_rate' => 15,
                'stock_reservation_minutes' => 15,
                'low_stock_threshold' => 5,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = StoreSetting::current();
        $this->assertSame(SmsProvider::Giantsms, $settings->active_sms_provider);
    }

    public function test_picking_an_sms_provider_with_no_credentials_configured_is_rejected(): void
    {
        config(['sms.providers.giantsms.api_token' => null, 'sms.providers.giantsms.sender_id' => null]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'active_sms_provider' => 'giantsms',
                'tax_rate' => 15,
                'stock_reservation_minutes' => 15,
                'low_stock_threshold' => 5,
            ])
            ->call('save')
            ->assertHasFormErrors(['active_sms_provider']);
    }

    public function test_tax_rate_over_100_percent_is_rejected(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'tax_rate' => 150,
                'stock_reservation_minutes' => 15,
                'low_stock_threshold' => 5,
            ])
            ->call('save')
            ->assertHasFormErrors(['tax_rate' => 'max']);
    }
}
