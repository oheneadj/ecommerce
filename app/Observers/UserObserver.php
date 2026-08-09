<?php

/**
 * Keeps the `public` disk in sync with `avatar_url` — deletes the old
 * avatar file when it's replaced or removed, and the current one when the
 * account is actually deleted, so neither ever leaves an orphaned file
 * behind in storage.
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserObserver
{
    public function saving(User $user): void
    {
        if (! $user->isDirty('avatar_url')) {
            return;
        }

        $original = $user->getOriginal('avatar_url');

        if ($original) {
            Storage::disk('public')->delete($original);
        }
    }

    /**
     * Fires on both soft delete and force delete — a soft-deleted account
     * is inaccessible everywhere in this app (no restore flow exists), so
     * its avatar is genuinely unused from that point on, not just hidden.
     */
    public function deleted(User $user): void
    {
        if ($user->avatar_url) {
            Storage::disk('public')->delete($user->avatar_url);
        }
    }
}
