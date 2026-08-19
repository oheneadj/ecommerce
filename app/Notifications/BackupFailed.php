<?php

/**
 * Alerts every Super Admin that a scheduled or manual backup run failed.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\BackupRuns\BackupRunResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sent by App\Listeners\RecordFailedBackup via SafeNotifier, same pattern
 * CriticalHealthAlert already uses. Mail + database only — unlike an
 * active outage (CriticalHealthAlert), a failed backup doesn't need SMS's
 * urgency, but it must never go unnoticed either, since the next attempt
 * is a full day (or week) away.
 */
class BackupFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly string $errorClass,
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
        return (new MailMessage)
            ->subject('Backup failed')
            ->greeting('A backup run failed')
            ->line("The most recent database/files backup did not complete successfully ({$this->errorClass}).")
            ->line('The next scheduled attempt will run automatically, but this is worth checking on if it keeps happening.')
            ->action('View backups', BackupRunResource::getUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        $message = "Backup failed ({$this->errorClass}).";

        // `title`/`status` are Filament's own expected keys — see the
        // matching note on CriticalHealthAlert::toDatabase().
        return [
            'title' => $message,
            'status' => 'danger',
            'message' => $message,
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('BackupFailed notification failed permanently', [
            'exception_class' => $exception::class,
        ]);
    }
}
