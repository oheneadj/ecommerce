<?php

/**
 * Tier 1 (config/schema) — asserts payment providers actually have credentials.
 */

declare(strict_types=1);

namespace App\HealthChecks;

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

        $channels = (array) config('payments.channels', []);
        $providers = (array) config('payments.providers', []);

        if ($channels === []) {
            return $result->failed('No payment channels are configured in config/payments.php. Fix: map at least one channel (e.g. mobile_money) to a provider.');
        }

        $unconfigured = [];

        foreach ($channels as $channel => $providerKey) {
            $credentials = (array) ($providers[$providerKey] ?? []);
            $hasCredentials = collect($credentials)->filter(fn ($value) => filled($value))->isNotEmpty();

            if (! $hasCredentials) {
                $unconfigured[] = "{$channel} (provider: {$providerKey})";
            }
        }

        if ($unconfigured === []) {
            return $result->ok('Every configured payment channel has credentials set.');
        }

        return $result
            ->failed('These payment channels have no credentials configured: '.implode(', ', $unconfigured).'. Fix: set the matching provider env vars (e.g. MOOLRE_API_KEY, PAYSTACK_SECRET_KEY).')
            ->meta(['unconfigured_channels' => $unconfigured]);
    }
}
