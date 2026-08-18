<?php

/**
 * Thrown when an order status change would skip a required state or reverse fulfillment.
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;
use Exception;

/**
 * Every legal transition is defined on OrderStatus::allowedNextStatuses() —
 * this fires whenever UpdateOrderStatus is asked for anything outside that
 * set, e.g. Pending straight to Delivered (skipping payment/stock
 * decrement entirely) or moving out of a terminal state.
 */
class InvalidOrderStatusTransitionException extends Exception
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct("Cannot move an order from \"{$from->label()}\" to \"{$to->label()}\".");
    }
}
