<?php

/**
 * Sends a notification without letting a delivery failure break the caller.
 */

declare(strict_types=1);

namespace App\Notifications\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * A rollback can't un-send an SMS, and a failed SMS/email send must never
 * be allowed to fail the order/payment/shipment/stock Action that
 * triggered it (technical-design-ecommerce.md §4a: external side effects
 * are dispatched after commit, and a delivery failure is the notification
 * provider's problem, not the business transaction's). Failures are
 * logged, never thrown.
 */
class SafeNotifier
{
    public static function send(mixed $notifiable, Notification $notification): void
    {
        try {
            NotificationFacade::send($notifiable, $notification);
        } catch (Throwable $e) {
            Log::warning('Notification delivery failed', [
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
