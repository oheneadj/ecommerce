<?php

/**
 * Thrown when a requested refund amount exceeds its parent payment's amount.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class RefundExceedsPaymentException extends Exception
{
    public function __construct(string $message = 'A refund cannot exceed its payment\'s amount, minus any amount already refunded.')
    {
        parent::__construct($message);
    }
}
