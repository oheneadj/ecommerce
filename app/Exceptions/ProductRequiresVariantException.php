<?php

/**
 * Thrown when trying to set a product Active while it has no variants.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * A Draft product can be saved with zero variants (it's a work in progress
 * and never customer-facing), but an Active one can't — every order line
 * references a specific variant, never a bare product, so nothing with
 * zero variants can actually be sold.
 */
class ProductRequiresVariantException extends Exception
{
    public function __construct(string $message = 'A product must have at least one variant before it can be set to Active.')
    {
        parent::__construct($message);
    }
}
