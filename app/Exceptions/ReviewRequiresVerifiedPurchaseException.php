<?php

/**
 * Thrown when a review is attempted without a completed purchase of the item.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ReviewRequiresVerifiedPurchaseException extends Exception
{
    public function __construct(string $message = 'You can only review a product you have purchased.')
    {
        parent::__construct($message);
    }
}
