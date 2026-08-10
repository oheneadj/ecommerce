<?php

/**
 * Shared channel-selection logic for order-related customer notifications.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whichever identifiers the recipient actually has determine delivery:
 * phone present → sms, email present → mail, both present → both. A
 * Google-only customer with no phone on file never receives sms — it
 * falls back to mail only. The "database" channel (surfaced in the
 * customer's account, and via the Filament bell for staff notifications)
 * is only added for a real registered User — an anonymous guest
 * notifiable has no row to attach a database notification to.
 *
 * Queued (ShouldQueue): actual delivery must never block the request that
 * triggered it — this matters most for HandlePaymentWebhook, a synchronous
 * HTTP endpoint the payment provider calls; blocking on an SMS/email
 * round-trip there risks the provider timing out and retrying the webhook.
 * Dispatch still only happens after the triggering transaction commits
 * (each call site wraps the send in `DB::afterCommit()`), so the queued
 * job never runs against uncommitted data. Requires a real queue worker in
 * production (`QUEUE_CONNECTION=database` is already configured) — with no
 * worker running, jobs sit in the `jobs` table undelivered.
 */
abstract class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        protected readonly Order $order,
    ) {
        $this->onQueue('notifications');
    }

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

    /**
     * Every retry attempt has been exhausted — the customer never received
     * this order update through any channel. Logged, not silently dropped,
     * so a delivery outage is at least visible to whoever's watching
     * laravel.log, even though there's no per-order "retry this" UI yet.
     */
    public function failed(Throwable $exception): void
    {
        Log::error(static::class.' failed permanently', [
            'order_id' => $this->order->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
