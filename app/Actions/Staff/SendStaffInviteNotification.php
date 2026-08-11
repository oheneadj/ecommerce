<?php

/**
 * (Re-)sends the set-password invite to a staff account.
 */

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\StaffInvited;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generates a real password-reset token via the same broker Fortify's
 * "forgot password" flow uses, and points it at the existing
 * `password.reset` page — zero new routes/pages needed to let a staff
 * member actually set their password. Reused by both the initial invite
 * (`InviteStaffMember`) and a "Resend invite"/re-enable action, so the
 * token+notify pair lives in exactly one place.
 */
class SendStaffInviteNotification
{
    use AsAction;

    public function handle(User $staff): void
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($staff);

        $url = route('password.reset', [
            'token' => $token,
            'email' => $staff->email,
        ]);

        $roleName = $staff->getRoleNames()->first();

        $staff->notify(new StaffInvited(
            UserRole::from($roleName),
            $url,
        ));
    }
}
