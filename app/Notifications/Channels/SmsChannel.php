<?php

/**
 * Custom notification channel delivering via the SmsGateway abstraction.
 */

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Sms\Contracts\SmsGateway;
use Illuminate\Notifications\Notification;

/**
 * Registered as the "sms" channel via Notification::extend() in
 * AppServiceProvider. Never calls Moolre/any vendor SDK directly — routes
 * through the same SmsGateway contract every other SMS-sending Action uses.
 */
readonly class SmsChannel
{
    public function __construct(
        private SmsGateway $gateway,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('sms', $notification);

        if (! is_string($phone) || $phone === '') {
            return;
        }

        $this->gateway->send($phone, $notification->toSms($notifiable));
    }
}
