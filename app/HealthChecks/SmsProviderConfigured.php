<?php

/**
 * Tier 1 (config/schema) — asserts the default SMS provider has credentials.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Enums\SmsProvider;
use App\Models\StoreSetting;
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

        $provider = StoreSetting::current()->active_sms_provider
            ?? SmsProvider::from((string) config('sms.default'));

        if ($provider->hasCredentialsConfigured()) {
            return $result->ok("The active SMS provider ({$provider->label()}) has credentials configured.");
        }

        return $result->failed("The active SMS provider ({$provider->label()}) has no credentials configured — phone OTP delivery will fail. Fix: set its environment variables (e.g. MOOLRE_API_KEY, GIANTSMS_TOKEN).");
    }
}
