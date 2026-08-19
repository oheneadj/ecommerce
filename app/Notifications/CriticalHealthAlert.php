<?php

/**
 * Alerts Super Admin that one or more critical health checks are failing.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Pages\SystemHealth;
use App\Notifications\Support\BrandedMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sent daily by `SendCriticalHealthAlert` for as long as any critical check
 * is failing — Super Admin can snooze it for 24 hours from the System
 * Health page if they've already seen it and are working on it. Database
 * channel surfaces it on the Filament admin bell; mail gives an off-panel
 * heads-up; sms is the fastest path for a genuinely critical outage, same
 * reasoning as `LowStockAlert`. `SmsChannel` no-ops gracefully if the
 * Super Admin has no phone on file (not collected by the CLI bootstrap
 * command).
 */
class CriticalHealthAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * @param  array<int, string>  $failures
     */
    public function __construct(
        private readonly array $failures,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'sms', 'database'];
    }

    /**
     * Lists every currently-failing check and links to the System Health page.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Critical system health failure')
            ->greeting('Critical health check failure')
            ->line('The following critical check(s) are currently failing:');

        foreach ($this->failures as $failure) {
            $message->line("- {$failure}");
        }

        return $message
            ->action('View system health', SystemHealth::getUrl())
            ->line('You can snooze this reminder for 24 hours from the System Health page.');
    }

    public function toSms(mixed $notifiable): string
    {
        $summary = count($this->failures) === 1
            ? "Critical check failing: {$this->failures[0]}"
            : count($this->failures).' critical checks are failing.';

        return BrandedMessage::sms($summary);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        $message = count($this->failures) === 1
            ? "Critical check failing: {$this->failures[0]}"
            : count($this->failures).' critical checks are failing.';

        return [
            'failures' => $this->failures,
            // `title`/`status` are Filament's own expected keys — the
            // admin bell (`->databaseNotifications()` in
            // AdminPanelProvider) reconstructs a Filament Notification
            // from this array via `Notification::fromArray()`, which
            // renders a blank title if this key is missing. `message` is
            // kept for anything reading the raw payload directly.
            'title' => $message,
            'status' => 'danger',
            'message' => $message,
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('CriticalHealthAlert failed permanently', [
            'failures' => $this->failures,
            'exception' => $exception->getMessage(),
        ]);
    }
}
