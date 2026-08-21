<?php

/**
 * Tier 1 (config/schema) — asserts error tracking (Sentry) has a DSN configured.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Deliberately a `warning()`, not a `failed()` — unlike payment/SMS
 * credentials, the app functions correctly without Sentry configured, so
 * this must never trip the critical-failure gate (`system:check
 * --critical`, the post-deploy alert) the way a genuinely broken
 * capability would. It's still worth surfacing on System Health, since an
 * unconfigured Sentry means production exceptions are only ever visible
 * in `laravel.log`, with nobody notified.
 */
class SentryConfigured extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        if (filled(config('sentry.dsn'))) {
            return $result->ok('Sentry is configured — unhandled exceptions are reported.');
        }

        return $result->warning('No Sentry DSN configured — unhandled exceptions are only visible in the server logs. See docs/HOWTO-setup-sentry.md.');
    }
}
