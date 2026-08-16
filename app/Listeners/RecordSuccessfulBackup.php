<?php

/**
 * Marks the in-progress backup run as successful once spatie/laravel-backup confirms it.
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\BackupStatus;
use App\Models\BackupRun;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupWasSuccessful;

/**
 * Only one BackupRun row is ever Running at a time (App\Jobs\RunBackupJob
 * is guarded by ->withoutOverlapping() on the schedule, and the manual
 * "Run now" action is disabled while one is already in progress) — so
 * "the most recent Running row" is always the right one to update,
 * without needing to thread a correlation ID through spatie's own event.
 */
class RecordSuccessfulBackup
{
    public function handle(BackupWasSuccessful $event): void
    {
        $run = BackupRun::query()->running()->latest('id')->first();

        if ($run === null) {
            return;
        }

        $destination = new BackupDestination(Storage::disk($event->diskName), $event->backupName, $event->diskName);
        $backup = $destination->newestBackup();

        $run->update([
            'status' => BackupStatus::Success,
            'disk' => $event->diskName,
            'remote_path' => $backup?->path(),
            'size_bytes' => $backup === null ? null : (int) $backup->sizeInBytes(),
            'completed_at' => now(),
        ]);
    }
}
