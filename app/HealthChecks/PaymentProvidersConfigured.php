<?php

/**
 * Tier 1 (config/schema) — asserts payment providers actually have credentials.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Enums\PaymentProvider;
use App\Models\StoreSetting;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Asserts presence only — never makes a live API call from a health check
 * (docs/TASK-system-health-checks.md constraints).
 */
class PaymentProvidersConfigured extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $provider = StoreSetting::current()->active_payment_provider
            ?? PaymentProvider::from((string) config('payments.default'));

        if ($provider->hasCredentialsConfigured()) {
            return $result->ok("The active payment provider ({$provider->label()}) has credentials configured.");
        }

        return $result->failed("The active payment provider ({$provider->label()}) has no credentials configured — checkout payments will fail. Fix: set its environment variables (e.g. MOOLRE_API_KEY, PAYSTACK_SECRET_KEY).");
    }
}
