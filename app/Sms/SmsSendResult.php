<?php

/**
 * Value object returned by every SmsGateway driver's send() call.
 */

declare(strict_types=1);

namespace App\Sms;

/**
 * Immutable outcome of a single SMS send attempt, independent of which provider handled it.
 */
final readonly class SmsSendResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public ?string $providerReference = null,
        public ?string $errorMessage = null,
        public array $rawResponse = [],
        public ?int $statusCode = null,
    ) {}
}
