<?php

/**
 * Tier 2 (operational heartbeat) — asserts a recent successful backup exists.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Enums\BackupStatus;
use App\Models\BackupRun;
use App\Models\StoreSetting;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Gives the pre-existing `backup_restore_tested` manual attestation
 * (docs/TASK-system-health-checks.md §5.1) a real automated signal to sit
 * alongside — that attestation only records that a restore was *tested*
 * at some point; this asserts backups are actually still *running*.
 */
class BackupIsRecent extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $settings = StoreSetting::current();

        if (! $settings->backup_auto_enabled || $settings->backup_frequency === null) {
            return $result->failed('Automatic backups are not configured. Fix: enable them from Settings → Store Settings → Backups.');
        }

        $latest = BackupRun::query()->where('status', BackupStatus::Success)->latest('completed_at')->first();

        if ($latest === null || $latest->completed_at === null) {
            return $result->failed('No backup has ever completed successfully. Fix: run one now from Settings → Backups.');
        }

        $allowedDays = $settings->backup_frequency->intervalInDays() + 1;

        if ($latest->completed_at->diffInDays(now()) > $allowedDays) {
            return $result->failed("The most recent successful backup is more than {$allowedDays} day(s) old. Fix: check Settings → Backups for recent failures.");
        }

        return $result->ok('A recent backup completed successfully.');
    }
}
