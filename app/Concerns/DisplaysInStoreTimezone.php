<?php

/**
 * Shared display formatting for timestamps shown to customers.
 */

declare(strict_types=1);

namespace App\Concerns;

use App\Models\StoreSetting;
use Carbon\CarbonInterface;

/**
 * Converts a stored (always-UTC, per CLAUDE.md) timestamp to the store's
 * configured display timezone (`StoreSetting::timezone`, default UTC) —
 * showing a raw UTC timestamp to a customer outside that offset can
 * display their order as placed on the wrong calendar day. Models using
 * this trait should expose one accessor per customer-facing timestamp via
 * `inStoreTimezone()`, mirroring how `HasFormattedMoney` exposes money.
 */
trait DisplaysInStoreTimezone
{
    protected function inStoreTimezone(?CarbonInterface $date): ?CarbonInterface
    {
        return $date?->setTimezone(StoreSetting::current()->timezone);
    }
}
