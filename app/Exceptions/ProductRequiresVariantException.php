<?php

/**
 * Thrown when attempting to create a product with no variants.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * A product can never be saved without at least one variant, since every
 * order line references a specific variant, never a bare product.
 */
class ProductRequiresVariantException extends Exception
{
    public function __construct(string $message = 'A product must be created with at least one variant.')
    {
        parent::__construct($message);
    }
}
