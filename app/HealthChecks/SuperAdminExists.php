<?php

/**
 * Tier 1 (config/schema) — asserts at least one Super Admin account exists.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Without a Super Admin, nobody can manage roles, view the health
 * dashboard, or record an attestation — this app has no other recovery
 * path (docs/infrastructure-deployment.md points at `app:create-super-admin`).
 */
class SuperAdminExists extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $exists = User::query()->role(UserRole::SuperAdmin->value)->exists();

        if ($exists) {
            return $result->ok('At least one Super Admin account exists.');
        }

        return $result->failed('No account holds the Super Admin role. Fix: php artisan app:create-super-admin');
    }
}
