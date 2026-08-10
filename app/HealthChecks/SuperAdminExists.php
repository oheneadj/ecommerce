<?php

/**
 * Tier 1 (config/schema) — asserts at least one Super Admin account exists.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Enums\UserRole;
use App\Notifications\Support\StaffRecipients;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Without a Super Admin, nobody can manage roles, view the health
 * dashboard, or record an attestation — this app has no other recovery
 * path (docs/infrastructure-deployment.md points at `app:create-super-admin`).
 *
 * Uses `StaffRecipients::forRole()` rather than a raw `User::role(...)`
 * query — this check runs on every single admin page load (via the admin
 * bar's critical-alert item), so it must never itself throw just because
 * a fresh install/test hasn't seeded the `super_admin` role row yet; that
 * state is exactly equivalent to "no Super Admin exists," not a crash.
 */
class SuperAdminExists extends Check
{
    /**
     * Fails if no account holds the Super Admin role (or the role hasn't
     * been seeded at all yet — both cases are treated as "none exist").
     */
    public function run(): Result
    {
        $result = Result::make();

        $exists = StaffRecipients::forRole(UserRole::SuperAdmin->value)->isNotEmpty();

        if ($exists) {
            return $result->ok('At least one Super Admin account exists.');
        }

        return $result->failed('No account holds the Super Admin role. Fix: php artisan app:create-super-admin');
    }
}
