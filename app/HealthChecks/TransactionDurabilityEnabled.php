<?php

/**
 * Tier 1 (config/schema) — asserts InnoDB's transaction log flushes on every commit.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * At `innodb_flush_log_at_trx_commit` values of 0 or 2, a committed
 * transaction can be lost on power failure — durability is not actually
 * guaranteed even though the transaction reported success.
 */
class TransactionDurabilityEnabled extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        if (DB::connection()->getDriverName() !== 'mysql') {
            return $result->ok('Not applicable — the active connection is not MySQL.');
        }

        $row = DB::selectOne("SHOW VARIABLES LIKE 'innodb_flush_log_at_trx_commit'");
        $value = $row->Value ?? null;

        if ($value === '1') {
            return $result->ok('innodb_flush_log_at_trx_commit is 1 — commits are durable.');
        }

        return $result
            ->failed("innodb_flush_log_at_trx_commit is '{$value}', not '1' — a committed transaction can be lost on power failure. Fix: SET GLOBAL innodb_flush_log_at_trx_commit = 1; and persist it in my.cnf.")
            ->meta(['innodb_flush_log_at_trx_commit' => $value]);
    }
}
