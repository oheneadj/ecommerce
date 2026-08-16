<?php

/**
 * Confirms to every Super Admin that a scheduled or manual backup run completed successfully.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\BackupRuns\BackupRunResource;
use App\Models\BackupRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sent by App\Listeners\RecordSuccessfulBackup via SafeNotifier — the
 * positive counterpart to BackupFailed, so a Super Admin gets a signal
 * either way rather than only ever hearing about backups when something
 * goes wrong. Mail + database only, same channels as BackupFailed.
 */
class BackupSucceeded extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly ?int $sizeBytes,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Backup completed')
            ->greeting('Backup completed successfully');

        $message->line($this->sizeBytes === null
            ? 'The database and uploaded files were backed up to Google Drive.'
            : 'The database and uploaded files ('.BackupRun::formatBytes($this->sizeBytes).') were backed up to Google Drive.');

        return $message->action('View backups', BackupRunResource::getUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'message' => 'Backup completed successfully.',
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('BackupSucceeded notification failed permanently', [
            'exception_class' => $exception::class,
        ]);
    }
}
