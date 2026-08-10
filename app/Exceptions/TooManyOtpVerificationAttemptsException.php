<?php

/**
 * Thrown when too many OTP verification attempts have been made for a phone number.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Distinct from the per-code 5-attempt lock in OtpCode::isUsable() (which
 * resets the moment a fresh code is requested) — this caps verification
 * attempts across an entire rolling window regardless of how many codes
 * were requested in between, closing the gap where repeated
 * request-code-then-guess cycles could otherwise bypass the per-code lock.
 */
class TooManyOtpVerificationAttemptsException extends Exception
{
    public function __construct(public readonly int $availableInSeconds)
    {
        parent::__construct("Too many attempts. Try again in {$availableInSeconds} seconds.");
    }
}
