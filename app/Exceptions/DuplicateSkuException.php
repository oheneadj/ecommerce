<?php

/**
 * Thrown when bulk variant generation would create a SKU that's already in use.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class DuplicateSkuException extends Exception
{
    public function __construct(string $sku)
    {
        parent::__construct("SKU \"{$sku}\" is already in use by another variant. Choose a different SKU prefix and try again.");
    }
}
