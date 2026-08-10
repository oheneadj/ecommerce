<?php

/**
 * Covers that a connection-level failure (timeout, DNS, connection
 * refused) talking to Moolre's SMS API is normalized into a failed
 * SmsSendResult rather than an uncaught exception — every caller
 * (SmsChannel, RequestOtp, SendCustomerSms) depends on send() never
 * throwing so their own SmsApiLog write always happens, even for this
 * failure mode.
 */

declare(strict_types=1);

namespace Tests\Feature\Sms;

use App\Sms\Drivers\MoolreSms;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoolreSmsConnectionFailureTest extends TestCase
{
    public function test_a_connection_failure_is_normalized_into_a_failed_result_instead_of_throwing(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('Connection timed out.');
        });

        $driver = new MoolreSms(apiKey: 'test-key', senderId: 'TestSender');

        $result = $driver->send('0244000000', 'Your code is 123456');

        $this->assertFalse($result->success);
        $this->assertNull($result->statusCode);
        $this->assertStringContainsString('Connection timed out', $result->errorMessage);
        $this->assertStringContainsString('Connection timed out', $result->rawResponse['error']);
    }
}
