<?php

/**
 * The normalized result of requesting a refund from a provider.
 */

declare(strict_types=1);

namespace App\Payments;

final readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public ?string $providerRefundReference = null,
        public ?string $errorMessage = null,
        public array $rawResponse = [],
    ) {}
}
