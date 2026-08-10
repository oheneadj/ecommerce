<?php

/**
 * Thrown when a cart line's quantity would exceed the variant's available stock.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * The cart itself never holds/reserves stock (BRD FR-3.1) — this only
 * caps what a customer can queue up as intent, against the variant's
 * current cached `stock`, so an obviously-unfulfillable cart never even
 * reaches checkout. A genuine race against other shoppers is still
 * enforced later by ReserveStockForOrder at checkout, not here.
 */
class CartQuantityExceedsStockException extends Exception
{
    /**
     * @param  int  $available  the variant's current stock, shown to the customer.
     */
    public function __construct(public readonly int $available)
    {
        parent::__construct("Only {$available} left in stock.");
    }
}
