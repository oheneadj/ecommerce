<?php

/**
 * Covers StockMovementPolicy — an immutable log: any staff role can
 * view/record one, nobody can update/delete/restore/force-delete an entry.
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockMovementPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_every_staff_role_can_view_and_record_stock_movements(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::StoreKeeper] as $role) {
            $user = $this->userWithRole($role);

            $this->assertTrue($user->can('viewAny', StockMovement::class));
            $this->assertTrue($user->can('create', StockMovement::class));
        }
    }

    public function test_nobody_can_update_delete_restore_or_force_delete_a_stock_movement(): void
    {
        $user = $this->userWithRole(UserRole::SuperAdmin);
        $movement = StockMovement::factory()->create();

        $this->assertFalse($user->can('update', $movement));
        $this->assertFalse($user->can('delete', $movement));
        $this->assertFalse($user->can('restore', $movement));
        $this->assertFalse($user->can('forceDelete', $movement));
    }
}
