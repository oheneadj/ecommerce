<?php

/**
 * Adds a second login method (Google) to an already-authenticated customer's account.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\AccountIdentifierAlreadyLinkedException;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Only ever called for a customer who is already authenticated (e.g. "Add
 * Google to my account" from settings) — never inferred automatically from
 * a bare login attempt, since that could incorrectly merge two different
 * people's data (BRD Section 4e).
 */
class LinkAccountIdentifier
{
    use AsAction;

    /**
     * @throws AccountIdentifierAlreadyLinkedException when the Google account is already linked elsewhere
     */
    public function handle(User $user, string $googleId, string $googleEmail): void
    {
        $existingOwner = User::query()->where('google_id', $googleId)->first();

        if ($existingOwner && $existingOwner->id !== $user->id) {
            throw new AccountIdentifierAlreadyLinkedException;
        }

        // One save, not two sequential ones — both fields belong to the
        // same row, so there's no reason to risk a partial update
        // (google_id set, email not) if something failed in between.
        $changes = ['google_id' => $googleId];

        if ($user->email === null) {
            $changes['email'] = $googleEmail;
            $changes['email_verified_at'] = now();
        }

        $user->forceFill($changes)->save();
    }
}
