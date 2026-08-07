<?php

/**
 * Covers StockReservationPolicy — entirely system-managed rows; staff can
 * view but never create, edit, or delete one by hand.
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockReservationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_every_staff_role_can_view_stock_reservations(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::StoreKeeper] as $role) {
            $this->assertTrue($this->userWithRole($role)->can('viewAny', StockReservation::class));
        }
    }

    public function test_nobody_can_create_update_delete_restore_or_force_delete_a_reservation_by_hand(): void
    {
        $user = $this->userWithRole(UserRole::SuperAdmin);
        $reservation = StockReservation::factory()->create();

        $this->assertFalse($user->can('create', StockReservation::class));
        $this->assertFalse($user->can('update', $reservation));
        $this->assertFalse($user->can('delete', $reservation));
        $this->assertFalse($user->can('restore', $reservation));
        $this->assertFalse($user->can('forceDelete', $reservation));
    }
}
