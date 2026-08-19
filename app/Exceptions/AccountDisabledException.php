<?php

/**
 * Thrown when a disabled account attempts to authenticate through any customer-facing login method.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * `disabled_at` previously only gated the Filament admin panel
 * (`User::canAccessPanel()`) — a disabled customer account could still log
 * in via phone OTP, Google, or password with no effect at all. This is
 * thrown at every customer-facing login entry point once the account is
 * resolved, before a session is established.
 */
class AccountDisabledException extends Exception
{
    public function __construct(string $message = 'This account has been disabled. Please contact support.')
    {
        parent::__construct($message);
    }
}
