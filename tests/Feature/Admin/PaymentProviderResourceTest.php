<?php

/**
 * Covers the Payment Providers admin screen — Super-Admin-only, auto-seeding
 * every known PaymentProvider case, toggling enabled state (blocked without
 * credentials, blocked when it would leave nothing enabled), and reordering.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentProvider;
use App\Enums\PaystackCheckoutMode;
use App\Enums\UserRole;
use App\Filament\Resources\PaymentProviders\Pages\ListPaymentProviders;
use App\Filament\Resources\PaymentProviders\PaymentProviderResource;
use App\Models\PaymentProviderSetting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentProviderResourceTest extends TestCase
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

    public function test_super_admin_can_view_the_list(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)->assertSuccessful();
    }

    public function test_admin_cannot_access_the_resource(): void
    {
        $this->actingAs($this->admin());

        $this->assertFalse(PaymentProviderResource::canViewAny());
    }

    public function test_every_known_provider_is_auto_seeded_on_first_load(): void
    {
        $this->actingAs($this->superAdmin());

        $this->assertSame(0, PaymentProviderSetting::query()->count());

        Livewire::test(ListPaymentProviders::class);

        $seeded = PaymentProviderSetting::query()->pluck('provider')->map(fn (PaymentProvider $provider): string => $provider->value)->sort()->values();
        $expected = collect(PaymentProvider::cases())->map(fn (PaymentProvider $case): string => $case->value)->sort()->values();

        $this->assertSame($expected->all(), $seeded->all());
    }

    public function test_syncing_twice_never_duplicates_rows(): void
    {
        PaymentProviderSetting::syncKnownProviders();
        PaymentProviderSetting::syncKnownProviders();

        $this->assertSame(count(PaymentProvider::cases()), PaymentProviderSetting::query()->count());
    }

    public function test_enabling_a_provider_with_no_credentials_configured_is_blocked(): void
    {
        config(['payments.providers.moolre.api_key' => null, 'payments.providers.moolre.webhook_secret' => null]);
        $setting = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre, 'enabled' => false]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)
            ->call('updateTableColumnState', 'enabled', (string) $setting->getKey(), true);

        $this->assertFalse($setting->fresh()->enabled);
    }

    public function test_enabling_a_provider_with_credentials_configured_succeeds(): void
    {
        config(['payments.providers.moolre.api_key' => 'test-key']);
        $setting = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre, 'enabled' => false]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)
            ->call('updateTableColumnState', 'enabled', (string) $setting->getKey(), true);

        $this->assertTrue($setting->fresh()->enabled);
    }

    public function test_disabling_the_last_enabled_provider_is_blocked(): void
    {
        config(['payments.providers.moolre.api_key' => 'test-key']);
        $setting = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre, 'enabled' => true]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)
            ->call('updateTableColumnState', 'enabled', (string) $setting->getKey(), false);

        $this->assertTrue($setting->fresh()->enabled);
    }

    public function test_disabling_a_provider_is_allowed_when_another_stays_enabled(): void
    {
        config(['payments.providers.moolre.api_key' => 'test-key', 'payments.providers.paystack.secret_key' => 'test-key']);
        $moolre = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre, 'enabled' => true]);
        PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Paystack, 'enabled' => true]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)
            ->call('updateTableColumnState', 'enabled', (string) $moolre->getKey(), false);

        $this->assertFalse($moolre->fresh()->enabled);
    }

    public function test_reordering_persists_sort_order(): void
    {
        $first = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre, 'sort_order' => 0]);
        $second = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Paystack, 'sort_order' => 1]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)
            ->call('reorderTable', [(string) $second->getKey(), (string) $first->getKey()]);

        $this->assertTrue($second->fresh()->sort_order < $first->fresh()->sort_order);
    }

    public function test_editing_moolres_row_has_no_checkout_mode_field(): void
    {
        $setting = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)
            ->mountAction(TestAction::make('edit')->table($setting))
            ->assertSchemaComponentDoesNotExist('checkout_mode');
    }

    /**
     * A brand-new row has `checkout_mode` genuinely null in the database
     * (Redirect is only the runtime *behavior* default, applied by
     * `PaymentProviderSetting::usesPaystackPopup()` treating anything
     * other than Popup as redirect — Filament's form default doesn't
     * backfill an existing null value when editing, only on a fresh
     * create form) — this asserts that runtime default holds.
     */
    public function test_a_paystack_row_with_no_checkout_mode_set_behaves_as_redirect(): void
    {
        $setting = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Paystack, 'checkout_mode' => null]);

        $this->assertFalse($setting->usesPaystackPopup());
    }

    public function test_saving_paystacks_checkout_mode_as_popup_persists_it(): void
    {
        $setting = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Paystack]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)
            ->mountAction(TestAction::make('edit')->table($setting))
            ->setActionData(['checkout_mode' => PaystackCheckoutMode::Popup->value])
            ->callMountedAction();

        $this->assertSame(PaystackCheckoutMode::Popup, $setting->fresh()->checkout_mode);
        $this->assertTrue($setting->fresh()->usesPaystackPopup());
    }

    public function test_saving_a_logo_upload_persists_the_file_path(): void
    {
        Storage::fake('public');
        $setting = PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListPaymentProviders::class)
            ->mountAction(TestAction::make('edit')->table($setting))
            ->setActionData(['logo_path' => UploadedFile::fake()->image('moolre.jpg')])
            ->callMountedAction();

        $this->assertNotNull($setting->fresh()->logo_path);
        Storage::disk('public')->assertExists($setting->fresh()->logo_path);
    }

    /**
     * Regression: replacing a provider's logo never cleaned up the old
     * file — unlike Brand's own logo (BrandObserver), nothing deleted the
     * previous upload, leaving it orphaned on the public disk forever.
     * Tests the observer directly against the model rather than through
     * Filament's FileUpload action (which pre-fills/re-processes the
     * upload in ways that obscure what's actually being asserted here).
     */
    public function test_replacing_a_logo_deletes_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('payment-providers/old.webp', 'old-contents');
        $setting = PaymentProviderSetting::factory()->create([
            'provider' => PaymentProvider::Moolre,
            'logo_path' => 'payment-providers/old.webp',
        ]);

        $setting->update(['logo_path' => 'payment-providers/new.webp']);

        Storage::disk('public')->assertMissing('payment-providers/old.webp');
    }
}
