<?php

/**
 * Thrown when a cart has attempted too many coupon codes too quickly.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Coupon codes have no other brute-force guard — this is what stops a
 * script from trying many codes in quick succession against one cart.
 */
class CouponAttemptsRateLimitedException extends Exception
{
    public function __construct(string $message = 'Too many coupon attempts. Please wait a few minutes and try again.')
    {
        parent::__construct($message);
    }
}
