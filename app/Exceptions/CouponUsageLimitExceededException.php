<?php

/**
 * Thrown when a coupon has already been used as many times as its usage_limit
 * or usage_limit_per_user allows.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CouponUsageLimitExceededException extends Exception
{
    public function __construct(string $message = 'This coupon has already reached its usage limit.')
    {
        parent::__construct($message);
    }
}
