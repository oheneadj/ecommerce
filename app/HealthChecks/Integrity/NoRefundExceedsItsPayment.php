<?php

/**
 * Tier 3 (data integrity) — CRITICAL: no refund.amount exceeds its parent
 * payment's amount.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

use Illuminate\Support\Facades\DB;

/**
 * A cross-row arithmetic constraint (technical-design §4g) — not
 * expressible as a DB check constraint. `ProcessRefund` enforces this at
 * write time; this catches drift from anything that bypassed it.
 */
class NoRefundExceedsItsPayment implements IntegrityCheck
{
    public function name(): string
    {
        return 'No refund exceeds its payment';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function remediationHint(): string
    {
        return 'A refund larger than its payment means money was refunded that was never charged — audit these payments/refunds by hand before touching the data.';
    }

    public function run(): IntegrityCheckOutcome
    {
        $ids = DB::table('refunds')
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->whereColumn('refunds.amount', '>', 'payments.amount')
            ->pluck('refunds.id')
            ->all();

        return $ids === [] ? IntegrityCheckOutcome::clean() : IntegrityCheckOutcome::violations($ids);
    }
}
