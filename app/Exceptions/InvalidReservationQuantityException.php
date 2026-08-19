<?php

/**
 * Thrown when a stock reservation is requested with a zero or negative quantity.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * A zero/negative reservation is meaningless — it would still insert a
 * `stock_reservations` row and pass the availability check trivially,
 * silently accepting nonsense input rather than reserving anything real.
 * Every real caller (checkout) always passes a positive cart-item
 * quantity — this only fires if something calls the Action directly with
 * a bad value.
 */
class InvalidReservationQuantityException extends Exception
{
    public function __construct(string $message = 'Reservation quantity must be at least 1.')
    {
        parent::__construct($message);
    }
}
