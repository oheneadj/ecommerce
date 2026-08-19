<?php

/**
 * Revokes every other active session for a user after a security-relevant account change.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Changing a password, enabling/disabling 2FA, or removing a passkey is
 * often done specifically to lock out an attacker who's still holding a
 * live session (e.g. a stolen password) — but none of those actions used
 * to touch the attacker's already-established session at all, so it kept
 * working until it naturally expired. This deletes every `sessions` row
 * for the user except the one making the current request, using the
 * database session driver's own store directly (this app's `session`
 * config always uses the `database` driver — see .env.example) rather
 * than a session-store-agnostic API, since Laravel has no single "log out
 * every other device" primitive that works across all guards/drivers.
 */
class LogOutOtherSessions
{
    use AsAction;

    public function handle(User $user, string $currentSessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
