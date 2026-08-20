<?php

/**
 * Fortify's configured password-reset action, shared by customer
 * self-service resets and the staff invite/re-enable flow.
 */

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password. A staff account
     * (Super Admin/Admin/Store Keeper) is held to the strong-password rule
     * unconditionally, not just in production — these are privileged
     * accounts regardless of environment. A customer keeps the existing
     * environment-based default (`AppServiceProvider::configureDefaults()`)
     * unaffected. Also stamps `email_verified_at` if not already set —
     * successfully using a mailed reset token proves inbox ownership,
     * whether that token came from an ordinary "forgot password" or a
     * staff invite/re-enable link.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        $isStaff = $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::StoreKeeper->value,
        ]);

        Validator::make($input, [
            'password' => $isStaff
                ? ['required', 'string', PasswordPolicy::strong(), 'confirmed']
                : $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // A "forgot password" reset is, in the most important real-world
        // case, someone recovering an account from an attacker who's
        // holding a stolen password and a still-live session — unlike
        // `LogOutOtherSessions` (used by an already-authenticated user
        // changing their own password), the person resetting here isn't
        // authenticated yet, so there's no "current session" of theirs to
        // preserve. Every existing session is purged.
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
