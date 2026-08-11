<?php

/**
 * Disables or re-enables a staff account.
 */

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Single entry point for both directions (row action and bulk action
 * alike), so this logic lives in exactly one place:
 * - Disabling kills any active session immediately (not just future
 *   logins) — flipping a DB flag alone wouldn't log out someone already
 *   mid-session on the `database` session driver.
 * - Re-enabling never restores access under the old password. A fresh
 *   unguessable placeholder replaces it and a new set-password invite goes
 *   out via `SendStaffInviteNotification` (mail + SMS) — matters most for
 *   "disabled over a security concern", where leaving the old password
 *   valid would defeat the point.
 * - The disabled/enabled transition itself is logged via `User`'s
 *   `LogsAdminActivity` (scoped to just `disabled_at`) — no manual
 *   activity() call needed here, Spatie Activitylog attributes it to the
 *   acting Super Admin automatically from the current auth guard.
 */
class SetStaffDisabledState
{
    use AsAction;

    public function handle(User $staff, bool $disabled): void
    {
        $staff->update(['disabled_at' => $disabled ? now() : null]);

        if ($disabled) {
            DB::table('sessions')->where('user_id', $staff->id)->delete();

            return;
        }

        $staff->update(['password' => Str::random(40)]);

        SendStaffInviteNotification::run($staff);
    }
}
