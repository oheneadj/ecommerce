<?php

/**
 * Thrown when attempting to check out a cart with no items.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class EmptyCartException extends Exception
{
    public function __construct(string $message = 'Cannot check out an empty cart.')
    {
        parent::__construct($message);
    }
}
