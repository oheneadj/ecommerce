<?php

/**
 * Thrown when a guest order cannot be claimed by the authenticated user.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class GuestOrderClaimException extends Exception
{
    public function __construct(string $message = 'This order cannot be claimed.')
    {
        parent::__construct($message);
    }
}
