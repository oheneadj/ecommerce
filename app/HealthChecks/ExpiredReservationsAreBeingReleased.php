<?php

/**
 * Tier 2 (operational heartbeat) — asserts ReleaseExpiredReservations is actually running.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * A subtler failure than "is cron running": the scheduler may be alive
 * while this specific job errors, is unregistered, or silently throws.
 * The symptom in production is stock reserved forever after abandoned
 * checkouts — the site shows sold-out while physical stock sits on the
 * shelf, with no error anywhere (docs/TASK-system-health-checks.md §3.1).
 */
class ExpiredReservationsAreBeingReleased extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $stuck = StockReservation::query()
            ->where('status', StockReservationStatus::Active)
            ->where('expires_at', '<', now()->subMinutes(15))
            ->get(['id']);

        if ($stuck->isEmpty()) {
            return $result->ok('No stock reservations are stuck past their expiry.');
        }

        $sample = $stuck->take(5)->pluck('id')->implode(', ');

        return $result
            ->failed("{$stuck->count()} stock reservation(s) are still active more than 15 minutes past expiry (IDs: {$sample}). Fix: verify the `release-expired-stock-reservations` scheduled job is actually running (php artisan schedule:list) and not throwing.")
            ->meta(['stuck_count' => $stuck->count(), 'sample_ids' => $stuck->take(5)->pluck('id')->all()]);
    }
}
