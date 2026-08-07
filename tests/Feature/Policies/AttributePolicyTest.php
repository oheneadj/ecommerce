<?php

/**
 * Covers AttributePolicy — the global attribute catalog. All staff can
 * view; Admin/Super Admin can manage; only Super Admin can force-delete.
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttributePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_every_staff_role_can_view_attributes(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::StoreKeeper] as $role) {
            $this->assertTrue($this->userWithRole($role)->can('viewAny', Attribute::class));
        }
    }

    public function test_store_keeper_cannot_create_update_or_delete_an_attribute(): void
    {
        $user = $this->userWithRole(UserRole::StoreKeeper);
        $attribute = Attribute::factory()->create();

        $this->assertFalse($user->can('create', Attribute::class));
        $this->assertFalse($user->can('update', $attribute));
        $this->assertFalse($user->can('delete', $attribute));
    }

    public function test_admin_can_create_update_and_delete_an_attribute(): void
    {
        $user = $this->userWithRole(UserRole::Admin);
        $attribute = Attribute::factory()->create();

        $this->assertTrue($user->can('create', Attribute::class));
        $this->assertTrue($user->can('update', $attribute));
        $this->assertTrue($user->can('delete', $attribute));
    }

    public function test_only_super_admin_can_force_delete_an_attribute(): void
    {
        $attribute = Attribute::factory()->create();

        $this->assertTrue($this->userWithRole(UserRole::SuperAdmin)->can('forceDelete', $attribute));
        $this->assertFalse($this->userWithRole(UserRole::Admin)->can('forceDelete', $attribute));
    }
}
