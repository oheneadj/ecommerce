<?php

/**
 * Thrown when a stock movement would leave a variant's stock below zero.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Physical stock can never be negative — a Damage/Adjustment/Sale movement
 * larger than what's actually on hand is a data-entry error, not a valid
 * correction, and must be rejected rather than silently producing a
 * negative `product_variants.stock` that then poisons low-stock alerts and
 * available-stock math downstream.
 */
class NegativeStockException extends Exception
{
    public function __construct(string $message = 'This movement would leave stock below zero.')
    {
        parent::__construct($message);
    }
}
