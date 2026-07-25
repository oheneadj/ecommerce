<?php

/**
 * The normalized result of verifying a payment's current status with a provider.
 */

declare(strict_types=1);

namespace App\Payments;

use App\Enums\PaymentStatus;

final readonly class PaymentVerificationResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerReference = null,
        public array $rawResponse = [],
    ) {}
}
