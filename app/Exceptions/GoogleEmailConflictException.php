<?php

/**
 * Thrown when a first-time Google sign-in matches an existing account whose email was never independently verified.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Deliberately generic wording aimed at self-resolution — never confirms
 * whose account it is, only that a conflict exists and how to get past it.
 */
class GoogleEmailConflictException extends Exception
{
    public function __construct(
        string $message = "An account already exists with this email, but it hasn't been verified yet. We've sent a new verification link — click it, then try Google again. Or log in with your original method and connect Google from Security settings."
    ) {
        parent::__construct($message);
    }
}
