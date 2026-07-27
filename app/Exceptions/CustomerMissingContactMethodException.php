<?php

/**
 * Thrown when trying to email or text a customer who has no email/phone on file.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CustomerMissingContactMethodException extends Exception {}
