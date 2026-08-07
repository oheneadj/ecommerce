<?php

/**
 * Thrown when a refund is attempted with a non-positive amount.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Distinct from RefundExceedsPaymentException — that one guards against an
 * amount that's too large, this one guards against an amount that's
 * meaningless (zero or negative) before it ever reaches the refund cap
 * check, the queued gateway call, or the proportional-restock math.
 */
class InvalidRefundAmountException extends Exception
{
    public function __construct(int $amount)
    {
        parent::__construct("Refund amount must be greater than zero (got {$amount}).");
    }
}
