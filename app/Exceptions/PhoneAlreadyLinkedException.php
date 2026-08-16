<?php

/**
 * Thrown when a phone number being linked to an account already belongs to a different one.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Deliberately generic wording — never confirms whose account the number
 * belongs to, only that it can't be linked here.
 */
class PhoneAlreadyLinkedException extends Exception
{
    public function __construct(string $message = 'This phone number is already linked to an account.')
    {
        parent::__construct($message);
    }
}
