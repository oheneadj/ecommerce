<?php

/**
 * Covers ActivityPolicy — the activity log is Super Admin only and
 * strictly read-only (entries are populated automatically, never by hand).
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_super_admin_can_view_the_activity_log(): void
    {
        $user = $this->userWithRole(UserRole::SuperAdmin);

        $this->assertTrue($user->can('viewAny', Activity::class));
    }

    public function test_admin_cannot_view_the_activity_log(): void
    {
        $user = $this->userWithRole(UserRole::Admin);

        $this->assertFalse($user->can('viewAny', Activity::class));
    }

    public function test_store_keeper_cannot_view_the_activity_log(): void
    {
        $user = $this->userWithRole(UserRole::StoreKeeper);

        $this->assertFalse($user->can('viewAny', Activity::class));
    }

    public function test_nobody_can_create_update_delete_restore_or_force_delete_an_activity_entry(): void
    {
        $user = $this->userWithRole(UserRole::SuperAdmin);
        $activity = new Activity;

        $this->assertFalse($user->can('create', Activity::class));
        $this->assertFalse($user->can('update', $activity));
        $this->assertFalse($user->can('delete', $activity));
        $this->assertFalse($user->can('restore', $activity));
        $this->assertFalse($user->can('forceDelete', $activity));
    }
}
