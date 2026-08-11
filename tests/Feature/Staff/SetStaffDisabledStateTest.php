<?php

/**
 * Covers SetStaffDisabledState — disabling kills the active session and
 * blocks panel access; re-enabling issues a fresh invite rather than
 * restoring the old password.
 */

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Actions\Staff\SetStaffDisabledState;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\StaffInvited;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SetStaffDisabledStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Admin->value, 'web');
    }

    private function staffMember(): User
    {
        $staff = User::factory()->create();
        $staff->assignRole(UserRole::Admin->value);

        return $staff;
    }

    public function test_disabling_sets_disabled_at_and_blocks_panel_access(): void
    {
        $staff = $this->staffMember();

        SetStaffDisabledState::run($staff, true);

        $staff->refresh();

        $this->assertNotNull($staff->disabled_at);
        $this->assertFalse($staff->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_disabling_deletes_the_staff_members_active_sessions(): void
    {
        $staff = $this->staffMember();
        DB::table('sessions')->insert([
            'id' => 'session-1',
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('data'),
            'last_activity' => time(),
        ]);

        SetStaffDisabledState::run($staff, true);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $staff->id)->count());
    }

    public function test_enabling_clears_disabled_at_and_restores_panel_access(): void
    {
        Notification::fake();

        $staff = $this->staffMember();
        $staff->update(['disabled_at' => now()]);

        SetStaffDisabledState::run($staff, false);

        $staff->refresh();

        $this->assertNull($staff->disabled_at);
        $this->assertTrue($staff->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_enabling_invalidates_the_old_password_and_sends_a_fresh_invite(): void
    {
        Notification::fake();

        $staff = $this->staffMember();
        $originalPassword = $staff->password;
        $staff->update(['disabled_at' => now()]);

        SetStaffDisabledState::run($staff, false);

        $staff->refresh();

        $this->assertNotSame($originalPassword, $staff->password);
        Notification::assertSentTo($staff, StaffInvited::class);
    }

    public function test_disabling_is_recorded_in_the_activity_log(): void
    {
        $staff = $this->staffMember();

        SetStaffDisabledState::run($staff, true);

        $this->assertDatabaseHas((new Activity)->getTable(), [
            'subject_type' => User::class,
            'subject_id' => $staff->id,
        ]);
    }
}
