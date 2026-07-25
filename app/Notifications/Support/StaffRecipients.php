<?php

/**
 * Resolves staff notification recipients by role, tolerating a role that hasn't been seeded yet.
 */

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

/**
 * A stock movement or stock adjustment must never fail just because a
 * given deployment hasn't created its Spatie roles yet (e.g. a fresh
 * install, or a test that doesn't need role setup for what it's actually
 * testing) — this returns an empty collection instead of letting
 * `User::role()` throw `RoleDoesNotExist`.
 */
class StaffRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function forRole(string $role): Collection
    {
        try {
            return User::role($role)->get();
        } catch (RoleDoesNotExist) {
            return new Collection;
        }
    }
}
