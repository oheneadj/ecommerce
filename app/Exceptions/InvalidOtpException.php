<?php

/**
 * Thrown when an OTP code fails verification.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Covers every way an OTP verification attempt can fail: no usable code exists
 * for the identifier, the code is expired/consumed, the code is locked out
 * after 5 failed attempts, or the submitted code simply doesn't match.
 */
class InvalidOtpException extends Exception
{
    public function __construct(string $message = 'The verification code is invalid or has expired.')
    {
        parent::__construct($message);
    }
}
