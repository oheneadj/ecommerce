<?php

/**
 * Alerts Super Admin that one or more critical health checks are failing.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Pages\SystemHealth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent daily by `SendCriticalHealthAlert` for as long as any critical check
 * is failing — Super Admin can snooze it for 24 hours from the System
 * Health page if they've already seen it and are working on it. Database
 * channel surfaces it on the Filament admin bell; mail gives an off-panel
 * heads-up, same reasoning as `LowStockAlert`.
 */
class CriticalHealthAlert extends Notification implements ShouldQueue
{
    use Queueable;

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
        return ['mail', 'database'];
    }

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

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'failures' => $this->failures,
            'message' => count($this->failures) === 1
                ? "Critical check failing: {$this->failures[0]}"
                : count($this->failures).' critical checks are failing.',
        ];
    }
}
