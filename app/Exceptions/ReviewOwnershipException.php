<?php

/**
 * Thrown when a customer attempts to edit or delete another customer's review.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ReviewOwnershipException extends Exception
{
    public function __construct(string $message = 'You can only manage your own reviews.')
    {
        parent::__construct($message);
    }
}
