<?php

/**
 * Thrown when a stock reservation cannot be satisfied by available stock.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * "Available" means the variant's cached stock minus everything currently
 * held by active reservations — thrown inside ReserveStockForOrder's locked
 * transaction so the caller never inserts a reservation the stock can't cover.
 */
class InsufficientStockException extends Exception
{
    public function __construct(string $message = 'Not enough stock available to reserve this quantity.')
    {
        parent::__construct($message);
    }
}
