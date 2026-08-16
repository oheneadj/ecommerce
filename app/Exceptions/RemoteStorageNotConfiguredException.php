<?php

/**
 * Thrown when a backup is attempted with no remote storage credentials configured.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class RemoteStorageNotConfiguredException extends Exception
{
    public function __construct(string $message = 'No remote storage provider has credentials configured.')
    {
        parent::__construct($message);
    }
}
