<?php

/**
 * Tier 1 (config/schema) — asserts every table uses the InnoDB storage engine.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * The single most important check in the system (docs/TASK-system-health-
 * checks.md §2.1): a MyISAM table accepts `DB::transaction()` without error
 * and rolls back nothing — every atomicity guarantee in this codebase
 * silently evaporates with no failure signal.
 */
class DatabaseEngineIsInnoDb extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        if (DB::connection()->getDriverName() !== 'mysql') {
            return $result->ok('Not applicable — the active connection is not MySQL.');
        }

        $nonInnoDbTables = DB::select("
            SELECT table_name FROM information_schema.tables
            WHERE table_schema = DATABASE() AND engine != 'InnoDB' AND table_type = 'BASE TABLE'
        ");

        if ($nonInnoDbTables === []) {
            return $result->ok('Every table uses the InnoDB storage engine.');
        }

        $tableNames = collect($nonInnoDbTables)->pluck('table_name')->implode(', ');

        return $result
            ->failed("These tables are not InnoDB and have no transaction/rollback guarantees: {$tableNames}. Fix: ALTER TABLE <name> ENGINE=InnoDB; for each one.")
            ->meta(['non_innodb_tables' => $tableNames]);
    }
}
