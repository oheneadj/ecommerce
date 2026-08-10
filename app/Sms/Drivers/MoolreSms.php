<?php

/**
 * Moolre's native SMS API driver.
 */

declare(strict_types=1);

namespace App\Sms\Drivers;

use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends SMS via Moolre's SMS API. Moolre is initially the only SMS provider,
 * but this Action-facing surface is identical for any future driver.
 */
readonly class MoolreSms implements SmsGateway
{
    public function __construct(
        private string $apiKey,
        private string $senderId,
    ) {}

    /**
     * Send a plain-text SMS via Moolre's API, returning a normalized result
     * regardless of the provider's own response shape.
     */
    public function send(string $to, string $message): SmsSendResult
    {
        // A connection-level failure (DNS, timeout, refused connection)
        // throws instead of returning a response — normalized into a
        // failure SmsSendResult, same as a gateway-reported error below,
        // so every caller's SmsApiLog write still happens for this
        // failure mode instead of being skipped entirely.
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->post('https://api.moolre.com/open/message/send', [
                    'sender' => $this->senderId,
                    'recipient' => $to,
                    'message' => $message,
                ]);
        } catch (ConnectionException $e) {
            return new SmsSendResult(
                success: false,
                errorMessage: $e->getMessage(),
                rawResponse: ['error' => $e->getMessage()],
                statusCode: null,
            );
        }

        if ($response->failed()) {
            return new SmsSendResult(
                success: false,
                errorMessage: $response->json('message') ?? 'Moolre SMS request failed.',
                rawResponse: $response->json() ?? [],
                statusCode: $response->status(),
            );
        }

        return new SmsSendResult(
            success: true,
            providerReference: $response->json('data.id'),
            rawResponse: $response->json() ?? [],
            statusCode: $response->status(),
        );
    }
}
