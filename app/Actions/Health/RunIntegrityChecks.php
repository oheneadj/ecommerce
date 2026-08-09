<?php

/**
 * Runs every Tier 3 (data integrity) check and stores its latest result.
 */

declare(strict_types=1);

namespace App\Actions\Health;

use App\HealthChecks\Integrity\IntegrityCheck;
use App\HealthChecks\Integrity\NoOrdersWithoutItems;
use App\HealthChecks\Integrity\NoProductsWithoutVariants;
use App\HealthChecks\Integrity\NoRefundExceedsItsPayment;
use App\HealthChecks\Integrity\NoReviewsWithoutVerifiedPurchase;
use App\HealthChecks\Integrity\NoSoftDeletedRecordHoldsOriginalUniqueValue;
use App\HealthChecks\Integrity\NoUsersWithoutIdentifier;
use App\HealthChecks\Integrity\StatusColumnsContainValidValues;
use App\HealthChecks\Integrity\StockCacheMatchesMovements;
use App\Models\IntegrityCheckResult;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Called only from the nightly `health:run-integrity-checks` command
 * (routes/console.php) — these are full-table aggregate scans and must
 * never run on page load (docs/TASK-system-health-checks.md Step 4).
 * Each check's result overwrites its previous row in `integrity_check_results`
 * — that table is a "last known result" with a timestamp, not a history log.
 */
class RunIntegrityChecks
{
    use AsAction;

    /**
     * @return Collection<int, IntegrityCheckResult>
     */
    public function handle(): Collection
    {
        $checks = [
            new StockCacheMatchesMovements,
            new NoUsersWithoutIdentifier,
            new NoRefundExceedsItsPayment,
            new NoOrdersWithoutItems,
            new NoProductsWithoutVariants,
            new NoReviewsWithoutVerifiedPurchase,
            new StatusColumnsContainValidValues,
            new NoSoftDeletedRecordHoldsOriginalUniqueValue,
        ];

        return collect($checks)->map(fn (IntegrityCheck $check) => $this->runOne($check));
    }

    private function runOne(IntegrityCheck $check): IntegrityCheckResult
    {
        $outcome = $check->run();

        $status = match (true) {
            $outcome->violationCount === 0 => 'ok',
            $check->severity() === 'critical' => 'failed',
            default => 'warning',
        };

        return IntegrityCheckResult::query()->updateOrCreate(
            ['check_name' => $check->name()],
            [
                'severity' => $check->severity(),
                'status' => $status,
                'violation_count' => $outcome->violationCount,
                'sample_ids' => $outcome->sampleIds,
                'message' => $outcome->violationCount > 0 ? $check->remediationHint() : null,
                'ran_at' => now(),
            ],
        );
    }
}
