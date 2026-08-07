<?php

/**
 * Covers UserPolicy — viewing/emailing customer accounts in the admin
 * panel. Admin/Super Admin only; Store Keeper's role never extends here.
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_and_super_admin_can_view_customers_and_send_them_communications(): void
    {
        $customer = User::factory()->create();

        foreach ([UserRole::Admin, UserRole::SuperAdmin] as $role) {
            $user = $this->userWithRole($role);

            $this->assertTrue($user->can('viewAny', User::class));
            $this->assertTrue($user->can('view', $customer));
            $this->assertTrue($user->can('sendCommunication', $customer));
        }
    }

    public function test_store_keeper_cannot_view_customers_or_send_them_communications(): void
    {
        $user = $this->userWithRole(UserRole::StoreKeeper);
        $customer = User::factory()->create();

        $this->assertFalse($user->can('viewAny', User::class));
        $this->assertFalse($user->can('view', $customer));
        $this->assertFalse($user->can('sendCommunication', $customer));
    }
}
