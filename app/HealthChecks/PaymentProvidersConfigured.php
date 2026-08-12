<?php

/**
 * Tier 1 (config/schema) — asserts payment providers actually have credentials.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Models\PaymentProviderSetting;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Asserts presence only — never makes a live API call from a health check
 * (docs/TASK-system-health-checks.md constraints). More than one provider
 * can be enabled at once (the customer picks one at checkout), so this
 * checks every *enabled* provider, not a single "active" one.
 */
class PaymentProvidersConfigured extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $enabled = PaymentProviderSetting::query()->enabledOrdered()->get();

        if ($enabled->isEmpty()) {
            return $result->failed('No payment provider is enabled — checkout payments will fail. Fix: enable at least one from Settings → Payment Providers.');
        }

        $unconfigured = $enabled->reject(fn (PaymentProviderSetting $setting): bool => $setting->provider->hasCredentialsConfigured());

        if ($unconfigured->isEmpty()) {
            return $result->ok('Every enabled payment provider has credentials configured.');
        }

        $names = $unconfigured->map(fn (PaymentProviderSetting $setting): string => $setting->provider->label())->implode(', ');

        return $result
            ->failed("These enabled payment providers have no credentials configured: {$names}. Fix: set their environment variables (e.g. MOOLRE_API_KEY, PAYSTACK_SECRET_KEY).")
            ->meta(['unconfigured_providers' => $unconfigured->map(fn (PaymentProviderSetting $setting): string => $setting->provider->value)->values()->all()]);
    }
}
