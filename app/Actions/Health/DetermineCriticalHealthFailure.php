<?php

/**
 * Answers one question: is any CRITICAL-severity check currently failing?
 */

declare(strict_types=1);

namespace App\Actions\Health;

use App\Models\HealthAttestation;
use App\Models\IntegrityCheckResult;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;

/**
 * Shared by the System Health page and the persistent admin banner
 * (docs/TASK-system-health-checks.md Step 5.3) so both agree on exactly
 * what "critical" means — a check that called `->failed()`/`->crashed()`
 * (never a mere `->warning()`), a stored Tier 3 result of `failed`, or a
 * required attestation that's missing or stale.
 *
 * The banner render hook fires on every single admin request, so the
 * result is cached briefly — Tier 1/2 checks are individually cheap, but
 * running every one of them on every admin page load is not something a
 * render hook should do uncached.
 */
class DetermineCriticalHealthFailure
{
    use AsAction;

    public function handle(): bool
    {
        return Cache::remember('health:critical-failure', 60, fn () => $this->check());
    }

    private function check(): bool
    {
        foreach (Health::registeredChecks() as $check) {
            if (! $check->shouldRun()) {
                continue;
            }

            $status = $check->run()->status;

            if ($status === Status::failed() || $status === Status::crashed()) {
                return true;
            }
        }

        if (IntegrityCheckResult::query()->where('status', 'failed')->exists()) {
            return true;
        }

        foreach (array_keys(HealthAttestation::REQUIRED) as $key) {
            $latest = HealthAttestation::latestFor($key);

            if ($latest === null || $latest->isStale()) {
                return true;
            }
        }

        return false;
    }
}
