<?php

/**
 * GiantSMS's native SMS API driver.
 */

declare(strict_types=1);

namespace App\Sms\Drivers;

use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * GiantSMS's "Authorization: Basic {token}" header is the raw API token
 * itself, not a base64(user:pass) pair the way HTTP Basic auth usually
 * works — the token is copied verbatim from the provider's dashboard.
 */
readonly class GiantSms implements SmsGateway
{
    private const BASE_URL = 'https://api.giantsms.com/api/v1/send';

    public function __construct(
        private string $apiToken,
        private string $senderId,
    ) {}

    /**
     * Send a plain-text SMS via GiantSMS's API, returning a normalized
     * result regardless of the provider's own response shape.
     */
    public function send(string $to, string $message): SmsSendResult
    {
        // A connection-level failure (DNS, timeout, refused connection)
        // throws instead of returning a response — normalized into a
        // failure SmsSendResult, same as a gateway-reported error below,
        // so every caller's SmsApiLog write still happens for this
        // failure mode instead of being skipped entirely.
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.$this->apiToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->post(self::BASE_URL, [
                    'from' => $this->senderId,
                    'to' => $to,
                    'msg' => $message,
                ]);
        } catch (ConnectionException $e) {
            return new SmsSendResult(
                success: false,
                errorMessage: $e->getMessage(),
                rawResponse: ['error' => $e->getMessage()],
                statusCode: null,
            );
        }

        if ($response->failed() || $response->json('status') !== true) {
            return new SmsSendResult(
                success: false,
                errorMessage: $response->json('message') ?? 'GiantSMS request failed.',
                rawResponse: $response->json() ?? [],
                statusCode: $response->status(),
            );
        }

        return new SmsSendResult(
            success: true,
            providerReference: $response->json('data.message_id'),
            rawResponse: $response->json() ?? [],
            statusCode: $response->status(),
        );
    }
}
