<?php

/**
 * Tier 1 (config/schema) — asserts the default SMS provider has credentials.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Asserts presence only — never makes a live API call from a health check
 * (docs/TASK-system-health-checks.md constraints). Phone OTP delivery
 * depends entirely on this being configured.
 */
class SmsProviderConfigured extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $defaultProvider = config('sms.default');
        $credentials = (array) config("sms.providers.{$defaultProvider}", []);
        $hasCredentials = collect($credentials)->filter(fn ($value) => filled($value))->isNotEmpty();

        if ($hasCredentials) {
            return $result->ok("The default SMS provider ({$defaultProvider}) has credentials configured.");
        }

        return $result->failed("The default SMS provider ({$defaultProvider}) has no credentials configured — phone OTP delivery will fail. Fix: set the provider's env vars (e.g. MOOLRE_API_KEY, MOOLRE_SENDER_ID).");
    }
}
