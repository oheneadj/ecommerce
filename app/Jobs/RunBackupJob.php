<?php

/**
 * Runs a full database + uploaded-files backup to Google Drive.
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BackupStatus;
use App\Enums\RemoteStorageProvider;
use App\Models\BackupRun;
use App\Models\StoreSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Both the scheduled trigger (App\Actions\Backup\RunScheduledBackup) and
 * the admin panel's manual "Run backup now" button dispatch this same
 * job — a full backup is exactly the kind of heavy/long-running work
 * CLAUDE.md §15 requires be queued rather than blocking a request or the
 * scheduler tick. Success/failure is recorded by
 * App\Listeners\RecordSuccessfulBackup / RecordFailedBackup, which react
 * to spatie/laravel-backup's own events — not by this job directly —
 * since those events fire the same way regardless of what triggered the
 * underlying artisan commands.
 */
class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    /**
     * @var array<int, int>
     */
    public array $backoff = [];

    public function __construct(
        private readonly ?int $triggeredBy = null,
    ) {
        $this->onQueue('backups');
    }

    public function handle(): void
    {
        if (! RemoteStorageProvider::GoogleDrive->hasCredentialsConfigured()) {
            BackupRun::query()->create([
                'status' => BackupStatus::Failed,
                'triggered_by' => $this->triggeredBy,
                'error_message' => 'RemoteStorageNotConfigured',
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            return;
        }

        BackupRun::query()->create([
            'status' => BackupStatus::Running,
            'triggered_by' => $this->triggeredBy,
            'started_at' => now(),
        ]);

        // Runtime override — StoreSetting::backup_retention_days is
        // admin-configurable, but spatie's own config file is a static
        // array; Laravel config is just in-memory at request time, so
        // mutating it here before backup:clean runs is safe and doesn't
        // touch the file on disk.
        config([
            'backup.cleanup.default_strategy.keep_all_backups_for_days' => StoreSetting::current()->backup_retention_days,
        ]);

        Artisan::call('backup:run', ['--disable-notifications' => true]);
        Artisan::call('backup:clean', ['--disable-notifications' => true]);
    }

    public function failed(Throwable $exception): void
    {
        BackupRun::query()->running()->latest('id')->first()?->update([
            'status' => BackupStatus::Failed,
            'error_message' => $exception::class,
            'completed_at' => now(),
        ]);

        Log::error('RunBackupJob failed permanently', [
            'triggered_by' => $this->triggeredBy,
            'exception_class' => $exception::class,
        ]);
    }
}
