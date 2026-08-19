<?php

/**
 * Thrown when a cart action is asked to add a zero or negative quantity.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * "Add 0 (or fewer)" has no sensible meaning for AddItemToCart the way it
 * does for UpdateCartItemQuantity (where 0 means "remove the line") —
 * every caller in this app hardcodes a positive quantity, so this only
 * ever fires if something calls the Action directly with a bad value.
 */
class InvalidCartQuantityException extends Exception
{
    public function __construct(string $message = 'Quantity must be at least 1.')
    {
        parent::__construct($message);
    }
}
