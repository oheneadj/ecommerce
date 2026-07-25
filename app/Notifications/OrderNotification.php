<?php

/**
 * Shared channel-selection logic for order-related customer notifications.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Whichever identifiers the recipient actually has determine delivery:
 * phone present → sms, email present → mail, both present → both. A
 * Google-only customer with no phone on file never receives sms — it
 * falls back to mail only. The "database" channel (surfaced in the
 * customer's account, and via the Filament bell for staff notifications)
 * is only added for a real registered User — an anonymous guest
 * notifiable has no row to attach a database notification to.
 */
abstract class OrderNotification extends Notification
{
    public function __construct(
        protected readonly Order $order,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = [];

        if ($notifiable->routeNotificationFor('mail')) {
            $channels[] = 'mail';
        }

        if ($notifiable->routeNotificationFor('sms')) {
            $channels[] = 'sms';
        }

        if ($notifiable instanceof User) {
            $channels[] = 'database';
        }

        return $channels;
    }
}
