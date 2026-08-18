<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BackupRun;
use App\Models\User;

/**
 * Backup history is Super-Admin-only, system-written: nobody creates,
 * edits, or deletes a row by hand — RunBackupJob/its listeners are the only
 * writers. Added for consistency with every other resource having its own
 * Policy (Section 18) even though BackupRunResource already gates access
 * directly via canAccess()/canCreate()/canEdit() overrides.
 */
class BackupRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, BackupRun $backupRun): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, BackupRun $backupRun): bool
    {
        return false;
    }

    public function delete(User $user, BackupRun $backupRun): bool
    {
        return false;
    }

    public function restore(User $user, BackupRun $backupRun): bool
    {
        return false;
    }

    public function forceDelete(User $user, BackupRun $backupRun): bool
    {
        return false;
    }
}
