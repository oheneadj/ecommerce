<?php

/**
 * Thrown when linking an identifier that already belongs to a different account.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Prevents linking a Google account (or other identifier) to a second
 * customer account when it's already attached to a different one.
 */
class AccountIdentifierAlreadyLinkedException extends Exception
{
    public function __construct(string $message = 'This account is already linked to a different login.')
    {
        parent::__construct($message);
    }
}
