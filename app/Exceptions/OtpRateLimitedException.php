<?php

/**
 * Thrown when an OTP request exceeds the per-phone rate limit.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Signals that a phone number has requested OTP codes too frequently — either
 * the 1-per-60-seconds or the 5-per-hour cap (Section 4.0 of the BRD).
 */
class OtpRateLimitedException extends Exception
{
    public function __construct(public readonly int $availableInSeconds)
    {
        parent::__construct("Too many OTP requests. Try again in {$availableInSeconds} seconds.");
    }
}
