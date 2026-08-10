<?php

/**
 * Lists every CRITICAL-severity check currently failing, by name.
 */

declare(strict_types=1);

namespace App\Actions\Health;

use App\Models\HealthAttestation;
use App\Models\IntegrityCheckResult;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Health\Checks\Check;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;
use Throwable;

/**
 * The single source of "what's critically broken right now" — shared by
 * the admin bar's alert item, the System Health page, and the daily
 * `SendCriticalHealthAlert` notification, so all three agree on exactly
 * what "critical" means: a check that called `->failed()`/`->crashed()`
 * (never a mere `->warning()`), a stored Tier 3 result of `failed`, or a
 * required attestation that's missing or stale.
 *
 * Called on every single admin request (via the admin bar), so the result
 * is cached briefly — Tier 1/2 checks are individually cheap, but running
 * every one of them on every page load is not something a render hook
 * should do uncached.
 *
 * @return array<int, string> one label per failing check, e.g.
 *                            "Super Admin Exists", "Stock cache matches movements", or an
 *                            attestation's label
 */
class ListCriticalHealthFailures
{
    use AsAction;

    /**
     * @return array<int, string>
     */
    public function handle(): array
    {
        return Cache::remember('health:critical-failures', 60, fn () => $this->list());
    }

    /**
     * @return array<int, string>
     */
    private function list(): array
    {
        $failures = [];

        foreach (Health::registeredChecks() as $check) {
            /** @var Check $check */
            if (! $check->shouldRun()) {
                continue;
            }

            // A check throwing (a bug in the check itself, an unseeded
            // dependency, anything) must never break this list — this
            // runs on every admin page load via the admin bar, so an
            // uncaught exception here would take down the whole panel,
            // not just report a health status. Same tolerance
            // `RunHealthChecksCommand` itself applies (its `runCheck()`
            // catches and reports "crashed" rather than propagating).
            try {
                $status = $check->run()->status;
            } catch (Throwable) {
                $failures[] = $check->getLabel();

                continue;
            }

            if ($status === Status::failed() || $status === Status::crashed()) {
                $failures[] = $check->getLabel();
            }
        }

        foreach (IntegrityCheckResult::query()->where('status', 'failed')->pluck('check_name') as $checkName) {
            $failures[] = $checkName;
        }

        foreach (HealthAttestation::REQUIRED as $key => $definition) {
            $latest = HealthAttestation::latestFor($key);

            if ($latest === null || $latest->isStale()) {
                $failures[] = $definition['label'];
            }
        }

        return $failures;
    }
}
