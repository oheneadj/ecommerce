<?php

/**
 * The in-app leg of a staff-composed broadcast to customers.
 */

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deliberately `database`-only — email and SMS legs of the same broadcast
 * are sent through the existing `SendEmailToCustomer`/`SendSmsToCustomer`
 * Actions (see `App\Jobs\FanOutCustomerBroadcast`), not through this
 * notification's own channel list, so there's exactly one code path per
 * channel rather than two competing ones. Read by the storefront's
 * notification bell/list (`App\Livewire\Storefront\NotificationIndicator`),
 * not the Filament admin bell — customers never log into the admin panel.
 */
class CustomerBroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly string $subject,
        private readonly string $message,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'subject' => $this->subject,
            'message' => $this->message,
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('CustomerBroadcastNotification failed permanently', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
