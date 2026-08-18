<?php

/**
 * Thrown when generating variants would leave a product with more than the configured limit.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ProductVariantLimitExceededException extends Exception
{
    public function __construct(int $limit)
    {
        parent::__construct("A product can have at most {$limit} variants. Reduce the attribute selection and try again.");
    }
}
