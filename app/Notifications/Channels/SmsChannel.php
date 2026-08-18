<?php

/**
 * Custom notification channel delivering via the SmsGateway abstraction.
 */

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\SmsApiLog;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsManager;
use Illuminate\Notifications\Notification;

/**
 * Registered as the "sms" channel via Notification::extend() in
 * AppServiceProvider. Never calls Moolre/any vendor SDK directly — routes
 * through the same SmsGateway contract every other SMS-sending Action uses.
 * This is the busiest of the three SMS call sites (every OrderPlaced/
 * OrderShipped/PaymentSucceeded/PaymentFailed/etc. notification goes
 * through here), so it logs to sms_api_logs the same way RequestOtp and
 * SendCustomerSms do (CLAUDE.md §21).
 */
readonly class SmsChannel
{
    public function __construct(
        private SmsGateway $gateway,
        private SmsManager $manager,
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

        $message = $notification->toSms($notifiable);
        $result = $this->gateway->send($phone, $message);

        SmsApiLog::query()->create([
            // The gateway itself doesn't know its own driver name (it's a
            // plain SmsGateway implementation) — read it from the Manager
            // that resolved it, so a store configured for GiantSMS isn't
            // misattributed to Moolre in every log row.
            'provider' => $this->manager->getDefaultDriver(),
            'action' => class_basename($notification),
            'recipient' => $phone,
            'request_payload' => ['recipient' => $phone, 'message' => $message],
            'response_payload' => $result->rawResponse,
            'status_code' => $result->statusCode,
        ]);
    }
}
