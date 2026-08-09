<?php

/**
 * Tier 2 (operational heartbeat) — asserts VerifyPendingPayments is actually running.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Any Payment still pending well beyond the polling grace period indicates
 * VerifyPendingPayments isn't running. Symptom: customer charged, order
 * never progresses (docs/TASK-system-health-checks.md §3.2).
 */
class PendingPaymentsAreBeingVerified extends Check
{
    private const GRACE_PERIOD_MINUTES = 30;

    public function run(): Result
    {
        $result = Result::make();

        $stuck = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(self::GRACE_PERIOD_MINUTES))
            ->get(['id']);

        if ($stuck->isEmpty()) {
            return $result->ok('No payments are stuck pending beyond the polling grace period.');
        }

        $sample = $stuck->take(5)->pluck('id')->implode(', ');

        return $result
            ->failed("{$stuck->count()} payment(s) are still pending more than ".self::GRACE_PERIOD_MINUTES." minutes after creation (IDs: {$sample}). Fix: verify the `verify-pending-payments` scheduled job is actually running (php artisan schedule:list) and not throwing.")
            ->meta(['stuck_count' => $stuck->count(), 'sample_ids' => $stuck->take(5)->pluck('id')->all()]);
    }
}
