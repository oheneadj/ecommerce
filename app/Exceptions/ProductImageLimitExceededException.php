<?php

/**
 * Thrown when an upload would leave a product with more images than the configured limit.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ProductImageLimitExceededException extends Exception
{
    public function __construct(int $limit)
    {
        parent::__construct("A product can have at most {$limit} images. Remove one before adding another.");
    }
}
