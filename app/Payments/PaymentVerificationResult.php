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
        /**
         * The amount the provider confirms was actually paid, in minor
         * units — null when the driver's verify endpoint doesn't expose
         * one (VerifyPaymentWithGateway treats null as "can't check,
         * don't block" rather than a mismatch). Paystack's own docs are
         * explicit that a caller "should also verify the amount to
         * ensure it matches the value of the service you are delivering"
         * — a status of "success" alone was previously trusted without
         * ever cross-checking this.
         */
        public ?int $amount = null,
        public array $rawResponse = [],
    ) {}
}
