<?php

/**
 * The single definition of "strong" for a privileged account's password.
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Consumed by both `AppServiceProvider` (the production-wide default for
 * every account) and `App\Actions\Fortify\ResetUserPassword` (applied
 * unconditionally for staff accounts, in every environment) — a plain
 * class rather than a trait, since neither of those is a natural place to
 * `use` a shared-behavior trait just to reach one rule definition.
 */
class PasswordPolicy
{
    public static function strong(): Password
    {
        return Password::min(12)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }
}
