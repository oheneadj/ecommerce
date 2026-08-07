<?php

/**
 * Covers that the "sms" notification channel logs every send to
 * sms_api_logs — the busiest of the three SMS call sites (every business
 * notification with a toSms() method goes through here), previously the
 * one gap left after auditing RequestOtp/SendCustomerSms.
 */

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Notifications\OrderPlaced;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_notification_via_the_sms_channel_logs_it(): void
    {
        $this->app->bind(SmsGateway::class, fn () => new class implements SmsGateway
        {
            public function send(string $to, string $message): SmsSendResult
            {
                return new SmsSendResult(success: true, providerReference: 'fake-ref', rawResponse: ['status' => 'ok'], statusCode: 200);
            }
        });

        $notifiable = (new AnonymousNotifiable)->route('sms', '0551234567');
        $notifiable->notify(new OrderPlaced(Order::factory()->create()));

        $this->assertDatabaseHas('sms_api_logs', [
            'recipient' => '0551234567',
            'action' => 'OrderPlaced',
        ]);
    }
}
