<?php

/**
 * Thrown when a restore is attempted against a backup that isn't a completed, successful run.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class BackupNotRestorableException extends Exception
{
    public function __construct(string $message = 'Only a successfully completed backup can be restored.')
    {
        parent::__construct($message);
    }
}
