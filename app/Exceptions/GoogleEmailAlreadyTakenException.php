<?php

/**
 * Thrown when connecting Google would adopt an email that already belongs to a different account.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Only reachable for a phone-only user (no email yet) connecting Google
 * whose Google account's email happens to already belong to someone else —
 * no accounts are merged here, this just refuses the email adoption rather
 * than crashing on the `users_email_unique` constraint.
 */
class GoogleEmailAlreadyTakenException extends Exception
{
    public function __construct(string $message = 'This Google account\'s email address is already in use by another account.')
    {
        parent::__construct($message);
    }
}
