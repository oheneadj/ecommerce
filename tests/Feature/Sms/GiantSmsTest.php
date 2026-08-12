<?php

/**
 * Covers the GiantSMS driver against its documented API shape: POST
 * https://api.giantsms.com/api/v1/send, `Authorization: Basic {token}`
 * (the raw API token, not a base64(user:pass) pair), JSON body
 * {from, to, msg}, and a `{"status": bool, ...}` response.
 */

declare(strict_types=1);

namespace Tests\Feature\Sms;

use App\Sms\Drivers\GiantSms;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GiantSmsTest extends TestCase
{
    public function test_a_successful_send_hits_the_documented_endpoint_with_the_documented_shape(): void
    {
        Http::fake([
            'api.giantsms.com/api/v1/send' => Http::response([
                'status' => true,
                'message' => 'Successfully Sent',
                'data' => ['message_id' => '83A54FED-7873-4C17-B54D-7003DFE88ED7'],
            ]),
        ]);

        $driver = new GiantSms(apiToken: 'test-token', senderId: 'TestSender');

        $result = $driver->send('0244000000', 'Your code is 123456');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.giantsms.com/api/v1/send'
                && $request->hasHeader('Authorization', 'Basic test-token')
                && $request['from'] === 'TestSender'
                && $request['to'] === '0244000000'
                && $request['msg'] === 'Your code is 123456';
        });

        $this->assertTrue($result->success);
        $this->assertSame('83A54FED-7873-4C17-B54D-7003DFE88ED7', $result->providerReference);
    }

    public function test_a_body_level_failure_status_is_treated_as_a_failed_send(): void
    {
        Http::fake([
            'api.giantsms.com/api/v1/send' => Http::response([
                'status' => false,
                'message' => 'Insufficient balance',
            ]),
        ]);

        $driver = new GiantSms(apiToken: 'test-token', senderId: 'TestSender');

        $result = $driver->send('0244000000', 'Hello');

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient balance', $result->errorMessage);
    }

    public function test_a_connection_failure_is_normalized_into_a_failed_result_instead_of_throwing(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('Connection timed out.');
        });

        $driver = new GiantSms(apiToken: 'test-token', senderId: 'TestSender');

        $result = $driver->send('0244000000', 'Hello');

        $this->assertFalse($result->success);
        $this->assertNull($result->statusCode);
        $this->assertStringContainsString('Connection timed out', $result->errorMessage);
    }
}
