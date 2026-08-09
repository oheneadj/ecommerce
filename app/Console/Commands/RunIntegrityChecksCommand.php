<?php

/**
 * Runs every Tier 3 data-integrity check and stores the results, nightly.
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Health\RunIntegrityChecks;
use App\Models\IntegrityCheckResult;
use Illuminate\Console\Command;

class RunIntegrityChecksCommand extends Command
{
    protected $signature = 'health:run-integrity-checks';

    protected $description = 'Run every Tier 3 data-integrity check and store the results (docs/TASK-system-health-checks.md Step 4).';

    public function handle(): int
    {
        $results = RunIntegrityChecks::run();

        $results->each(function (IntegrityCheckResult $result): void {
            $line = "{$result->check_name}: {$result->status}";

            match ($result->status) {
                'ok' => $this->info($line),
                'warning' => $this->warn($line),
                default => $this->error("{$line} ({$result->violation_count} affected)"),
            };
        });

        return self::SUCCESS;
    }
}
