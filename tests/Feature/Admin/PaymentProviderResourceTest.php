<?php

/**
 * Covers the Payment Providers admin screen — Super-Admin-only, auto-seeding
 * every known PaymentProvider case, toggling enabled state (blocked without
 * credentials, blocked when it would leave nothing enabled), and reordering.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Filament\Resources\PaymentProviders\Pages\ListPaymentProviders;
use App\Filament\Resources\PaymentProviders\PaymentProviderResource;
use App\Models\PaymentProviderSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
