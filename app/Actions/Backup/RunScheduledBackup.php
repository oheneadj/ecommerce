<?php

/**
 * Dispatches a backup run if one is actually due, per Store Settings.
 */

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Jobs\RunBackupJob;
use App\Models\BackupRun;
use App\Models\StoreSetting;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Scheduled daily (routes/console.php), but "daily" there is just how
 * often this check runs — whether a backup is actually due depends on
 * `StoreSetting::backup_auto_enabled`/`backup_frequency`. A no-op silent
 * return covers: auto-backup switched off, or a "weekly" schedule whose
 * last successful run is still inside its 7-day window. Same
 * self-guarding shape SendCriticalHealthAlert already uses for its own
 * snooze.
 */
class RunScheduledBackup
{
    use AsAction;

    public function handle(): void
    {
        $settings = StoreSetting::current();

        $frequency = $settings->backup_frequency;

        if (! $settings->backup_auto_enabled || $frequency === null) {
            return;
        }

        $lastSuccessful = BackupRun::query()->successful()->latest('completed_at')->first();

        if ($lastSuccessful !== null && $lastSuccessful->completed_at?->diffInDays(now()) < $frequency->intervalInDays()) {
            return;
        }

        RunBackupJob::dispatch();
    }
}
