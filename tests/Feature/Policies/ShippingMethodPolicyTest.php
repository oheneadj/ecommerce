<?php

/**
 * Covers ShippingMethodPolicy — checkout/pricing configuration,
 * Admin/Super Admin only (Store Keeper's role is inventory-only).
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShippingMethodPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_and_super_admin_can_manage_shipping_methods(): void
    {
        $shippingMethod = ShippingMethod::factory()->create();

        foreach ([UserRole::Admin, UserRole::SuperAdmin] as $role) {
            $user = $this->userWithRole($role);

            $this->assertTrue($user->can('viewAny', ShippingMethod::class));
            $this->assertTrue($user->can('create', ShippingMethod::class));
            $this->assertTrue($user->can('update', $shippingMethod));
            $this->assertTrue($user->can('delete', $shippingMethod));
            $this->assertTrue($user->can('restore', $shippingMethod));
        }
    }

    public function test_store_keeper_cannot_view_or_manage_shipping_methods(): void
    {
        $user = $this->userWithRole(UserRole::StoreKeeper);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->assertFalse($user->can('viewAny', ShippingMethod::class));
        $this->assertFalse($user->can('create', ShippingMethod::class));
        $this->assertFalse($user->can('update', $shippingMethod));
        $this->assertFalse($user->can('delete', $shippingMethod));
    }

    public function test_only_super_admin_can_force_delete_a_shipping_method(): void
    {
        $shippingMethod = ShippingMethod::factory()->create();

        $this->assertTrue($this->userWithRole(UserRole::SuperAdmin)->can('forceDelete', $shippingMethod));
        $this->assertFalse($this->userWithRole(UserRole::Admin)->can('forceDelete', $shippingMethod));
    }
}
