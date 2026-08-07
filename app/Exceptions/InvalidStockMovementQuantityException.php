<?php

/**
 * Thrown when a stock movement is recorded with a quantity of zero.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * A zero-quantity movement changes nothing but still writes a row to the
 * immutable stock_movements ledger — meaningless noise that would confuse
 * anyone reading the history back later.
 */
class InvalidStockMovementQuantityException extends Exception
{
    public function __construct(string $message = 'A stock movement quantity cannot be zero.')
    {
        parent::__construct($message);
    }
}
