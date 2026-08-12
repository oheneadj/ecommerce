<?php

/**
 * The normalized result of initiating a payment with a provider.
 */

declare(strict_types=1);

namespace App\Payments;

/**
 * Every driver returns this shape regardless of the provider's own response
 * format, so Actions never branch on a specific vendor's payload.
 */
final readonly class PaymentInitiationResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public ?string $providerReference = null,
        public ?string $redirectUrl = null,
        /**
         * Paystack-specific: the `access_code` from `/transaction/initialize`,
         * used by the frontend to open Paystack's popup checkout via
         * `PaystackPop.resumeTransaction(accessCode)` instead of redirecting.
         * Always null for every provider/flow that doesn't support popup
         * checkout (currently everything except Paystack).
         */
        public ?string $accessCode = null,
        public ?string $errorMessage = null,
        public array $rawResponse = [],
    ) {}
}
