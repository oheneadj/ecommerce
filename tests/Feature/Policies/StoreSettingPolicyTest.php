<?php

/**
 * Covers StoreSettingPolicy — deployment-wide config, Super Admin only.
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreSettingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_super_admin_can_view_and_update_store_settings(): void
    {
        $user = $this->userWithRole(UserRole::SuperAdmin);
        $settings = StoreSetting::current();

        $this->assertTrue($user->can('viewAny', StoreSetting::class));
        $this->assertTrue($user->can('update', $settings));
    }

    public function test_admin_cannot_view_or_update_store_settings(): void
    {
        $user = $this->userWithRole(UserRole::Admin);
        $settings = StoreSetting::current();

        $this->assertFalse($user->can('viewAny', StoreSetting::class));
        $this->assertFalse($user->can('update', $settings));
    }

    public function test_store_keeper_cannot_view_or_update_store_settings(): void
    {
        $user = $this->userWithRole(UserRole::StoreKeeper);
        $settings = StoreSetting::current();

        $this->assertFalse($user->can('viewAny', StoreSetting::class));
        $this->assertFalse($user->can('update', $settings));
    }

    public function test_nobody_can_create_delete_restore_or_force_delete_store_settings(): void
    {
        $user = $this->userWithRole(UserRole::SuperAdmin);
        $settings = StoreSetting::current();

        $this->assertFalse($user->can('create', StoreSetting::class));
        $this->assertFalse($user->can('delete', $settings));
        $this->assertFalse($user->can('restore', $settings));
        $this->assertFalse($user->can('forceDelete', $settings));
    }
}
