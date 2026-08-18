<?php

/**
 * Marks the in-progress backup run as successful once spatie/laravel-backup confirms it.
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\BackupStatus;
use App\Enums\UserRole;
use App\Models\BackupRun;
use App\Notifications\BackupSucceeded;
use App\Notifications\Support\SafeNotifier;
use App\Notifications\Support\StaffRecipients;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupWasSuccessful;

/**
 * Only one BackupRun row is ever Running at a time — App\Jobs\RunBackupJob
 * holds a cache lock for its entire run, so a second dispatch (scheduled or
 * manual) never gets this far concurrently — so "the most recent Running
 * row" is always the right one to update, without needing to thread a
 * correlation ID through spatie's own event.
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
        $sizeBytes = $backup === null ? null : (int) $backup->sizeInBytes();

        $run->update([
            'status' => BackupStatus::Success,
            'disk' => $event->diskName,
            'remote_path' => $backup?->path(),
            'size_bytes' => $sizeBytes,
            'completed_at' => now(),
        ]);

        $notification = new BackupSucceeded($sizeBytes);

        foreach (StaffRecipients::forRole(UserRole::SuperAdmin->value) as $superAdmin) {
            SafeNotifier::send($superAdmin, $notification);
        }
    }
}
