<?php

/**
 * Thrown when a coupon fails a validation rule (inactive, expired, out of
 * scope, or below its minimum order amount).
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidCouponException extends Exception
{
    public function __construct(string $message = 'This coupon cannot be applied to this order.')
    {
        parent::__construct($message);
    }
}
