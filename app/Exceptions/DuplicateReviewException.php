<?php

/**
 * Thrown when a second review is attempted for the same purchased line item.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class DuplicateReviewException extends Exception
{
    public function __construct(string $message = 'You have already reviewed this purchase.')
    {
        parent::__construct($message);
    }
}
