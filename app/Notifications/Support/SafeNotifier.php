<?php

/**
 * Sends a notification without letting a dispatch failure break the caller.
 */

declare(strict_types=1);

namespace App\Notifications\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * A rollback can't un-send an SMS, and a failed send must never be allowed
 * to fail the order/payment/shipment/stock Action that triggered it
 * (technical-design-ecommerce.md §4a: external side effects are dispatched
 * after commit, and a delivery failure is the notification provider's
 * problem, not the business transaction's).
 *
 * All notifications implement ShouldQueue, so `Notification::send()` here
 * only pushes a job onto the `jobs` table — this catches a failure in that
 * push itself (e.g. the queue connection is unreachable), not a failure in
 * actual delivery. Real delivery failures happen later, inside the queue
 * worker, and are handled by the queue's own retry/`failed()` mechanism —
 * requires a worker process actually running in production.
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
