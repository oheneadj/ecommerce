<?php

/**
 * Thrown when a review rating falls outside the allowed 1-5 star range.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidReviewRatingException extends Exception
{
    public function __construct(string $message = 'Rating must be between 1 and 5.')
    {
        parent::__construct($message);
    }
}
