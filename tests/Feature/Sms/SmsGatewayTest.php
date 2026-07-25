<?php

declare(strict_types=1);

namespace Tests\Feature\Sms;

use App\Actions\Auth\RequestOtp;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsManager;
use App\Sms\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_sms_gateway_driver_resolves_without_action_changes(): void
    {
        $fake = new class implements SmsGateway
        {
            public static ?string $lastMessage = null;

            public function send(string $to, string $message): SmsSendResult
            {
                self::$lastMessage = $message;

                return new SmsSendResult(success: true, providerReference: 'fake-ref');
            }
        };

        // Registered via the same public Manager::extend() point a real new
        // SMS provider would use — RequestOtp itself is never touched.
        $this->app->make(SmsManager::class)->extend('fake', fn () => $fake);
        config(['sms.default' => 'fake']);
        $this->app->bind(SmsGateway::class, fn ($app) => $app->make(SmsManager::class)->driver());

        RequestOtp::run('0551234567');

        $this->assertNotNull($fake::$lastMessage);
        $this->assertStringContainsString('login code', $fake::$lastMessage);
    }
}
