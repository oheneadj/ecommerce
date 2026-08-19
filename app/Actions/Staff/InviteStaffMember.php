<?php

/**
 * Creates a new staff account and sends the set-password invite.
 */

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Never a null password — a random, unguessable placeholder
 * (`Str::random(40)`, hashed automatically by User's `password` cast, same
 * as every other User creation in this app) instead, so there's no
 * null-password edge case in credential checking to worry about. The
 * account can't be logged into until the invite link is used. `$role` is
 * also restricted by the calling form's Select options and its own
 * validation rule, but this Action doesn't trust either alone — Super
 * Admin accounts are CLI-only (never created from this panel), so this is
 * the actual guarantee, not just a form-level convenience restriction.
 */
class InviteStaffMember
{
    use AsAction;

    public function handle(string $name, string $email, string $phone, UserRole $role): User
    {
        if (! in_array($role, [UserRole::Admin, UserRole::StoreKeeper], true)) {
            throw new RuntimeException('Staff accounts can only be created with the Admin or Store Keeper role.');
        }

        $staff = User::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Str::random(40),
        ]);

        $staff->assignRole($role->value);

        SendStaffInviteNotification::run($staff);

        return $staff;
    }
}
