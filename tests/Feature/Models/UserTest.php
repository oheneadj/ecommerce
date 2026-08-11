<?php

/**
 * Covers User::scopeCustomers()/scopeStaff() — the definitions of "customer"
 * and "manageable staff" reused by the Customers/Staff admin resources and
 * customer-broadcast targeting — plus the disabled-account panel gate.
 */

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_scope_excludes_accounts_holding_any_staff_role(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $customer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        $ids = User::query()->customers()->pluck('id');

        $this->assertTrue($ids->contains($customer->id));
        $this->assertFalse($ids->contains($admin->id));
    }

    public function test_staff_scope_includes_admin_and_store_keeper_but_never_super_admin(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        Role::findOrCreate(UserRole::Admin->value, 'web');
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);
        $storeKeeper = User::factory()->create();
        $storeKeeper->assignRole(UserRole::StoreKeeper->value);
        $customer = User::factory()->create();

        $ids = User::query()->staff()->pluck('id');

        $this->assertTrue($ids->contains($admin->id));
        $this->assertTrue($ids->contains($storeKeeper->id));
        $this->assertFalse($ids->contains($superAdmin->id));
        $this->assertFalse($ids->contains($customer->id));
    }

    public function test_a_disabled_staff_account_cannot_access_the_admin_panel(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $admin = User::factory()->create(['disabled_at' => now()]);
        $admin->assignRole(UserRole::Admin->value);

        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_an_enabled_staff_account_can_access_the_admin_panel(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $admin = User::factory()->create(['disabled_at' => null]);
        $admin->assignRole(UserRole::Admin->value);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }
}
