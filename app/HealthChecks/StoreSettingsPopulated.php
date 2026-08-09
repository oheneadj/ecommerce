<?php

/**
 * Tier 1 (config/schema) — WARNING: asserts branding/contact fields are set.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Models\StoreSetting;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * A fresh install passing this means branding was actually configured,
 * not left at whatever the seeder/migration defaults happened to be.
 */
class StoreSettingsPopulated extends Check
{
    public function run(): Result
    {
        $result = Result::make();
        $store = StoreSetting::current();

        $missing = collect([
            'business_name' => $store->business_name,
            'logo_path' => $store->logo_path,
            'contact_email' => $store->contact_email,
            'contact_phone' => $store->contact_phone,
        ])->filter(fn ($value) => blank($value))->keys();

        if ($missing->isEmpty()) {
            return $result->ok('Business name, logo, and contact details are all set.');
        }

        return $result
            ->warning('These store settings are not set: '.$missing->implode(', ').'. Fix: fill them in via Manage Store Settings in the admin panel.')
            ->meta(['missing_fields' => $missing->all()]);
    }
}
