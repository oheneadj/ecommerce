<?php

/**
 * Marks the in-progress backup run as failed and alerts every Super Admin.
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\BackupStatus;
use App\Enums\UserRole;
use App\Models\BackupRun;
use App\Notifications\BackupFailed;
use App\Notifications\Support\SafeNotifier;
use App\Notifications\Support\StaffRecipients;
use Spatie\Backup\Events\BackupHasFailed;

/**
 * Only one BackupRun row is ever Running at a time — see
 * App\Jobs\RunBackupJob's own docblock for why (a held cache lock, not just
 * the scheduler's own overlap guard) — so "the most recent Running row" is
 * always the right one here too.
 */
class RecordFailedBackup
{
    public function handle(BackupHasFailed $event): void
    {
        $errorClass = $event->exception::class;

        $run = BackupRun::query()->running()->latest('id')->first();

        $run?->update([
            'status' => BackupStatus::Failed,
            'error_message' => $errorClass,
            'completed_at' => now(),
        ]);

        $notification = new BackupFailed($errorClass);

        foreach (StaffRecipients::forRole(UserRole::SuperAdmin->value) as $superAdmin) {
            SafeNotifier::send($superAdmin, $notification);
        }
    }
}
