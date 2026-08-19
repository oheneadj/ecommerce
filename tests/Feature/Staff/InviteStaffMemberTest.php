<?php

/**
 * Covers InviteStaffMember + SendStaffInviteNotification — creating a
 * staff account and sending the set-password invite.
 */

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Actions\Staff\InviteStaffMember;
use App\Actions\Staff\SendStaffInviteNotification;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\StaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InviteStaffMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Admin->value, 'web');
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
    }

    public function test_it_creates_the_staff_account_with_the_given_role(): void
    {
        Notification::fake();

        $staff = InviteStaffMember::run('Jane Doe', 'jane@example.com', '0551234567', UserRole::Admin);

        $this->assertSame('Jane Doe', $staff->name);
        $this->assertSame('jane@example.com', $staff->email);
        $this->assertSame('0551234567', $staff->phone);
        $this->assertTrue($staff->hasRole(UserRole::Admin->value));
    }

    public function test_the_account_cannot_be_logged_into_with_a_known_password(): void
    {
        Notification::fake();

        $staff = InviteStaffMember::run('Jane Doe', 'jane@example.com', '0551234567', UserRole::StoreKeeper);

        $this->assertFalse($this->attemptLogin($staff->email, 'password'));
    }

    public function test_it_sends_the_invite_notification_via_mail_and_sms(): void
    {
        Notification::fake();

        $staff = InviteStaffMember::run('Jane Doe', 'jane@example.com', '0551234567', UserRole::Admin);

        Notification::assertSentTo($staff, StaffInvited::class, function (StaffInvited $notification, array $channels) {
            return in_array('mail', $channels, true) && in_array('sms', $channels, true);
        });
    }

    public function test_resend_invite_generates_a_working_reset_link(): void
    {
        Notification::fake();

        $staff = InviteStaffMember::run('Jane Doe', 'jane@example.com', '0551234567', UserRole::Admin);

        SendStaffInviteNotification::run($staff);

        Notification::assertSentTimes(StaffInvited::class, 2);
    }

    /**
     * Bug hunt regression: the calling form's Select only ever rendered
     * Admin/Store Keeper as options, which this Action's own docblock
     * claimed as a reason it "doesn't trust that alone" — but nothing
     * here actually enforced it, so calling this Action directly (or via
     * a manipulated form payload) with UserRole::SuperAdmin silently
     * succeeded, creating a Super Admin account outside the CLI-only path
     * this app documents.
     */
    public function test_it_refuses_to_create_a_super_admin_account(): void
    {
        Notification::fake();

        $this->expectException(RuntimeException::class);

        try {
            InviteStaffMember::run('Eve', 'eve@example.com', '0551234567', UserRole::SuperAdmin);
        } finally {
            $this->assertNull(User::query()->where('email', 'eve@example.com')->first());
        }
    }

    private function attemptLogin(string $email, string $password): bool
    {
        return $this->app['auth']->guard()->attempt(['email' => $email, 'password' => $password]);
    }
}
