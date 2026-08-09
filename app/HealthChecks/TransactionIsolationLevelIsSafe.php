<?php

/**
 * Tier 1 (config/schema) — asserts the DB isolation level is at least READ COMMITTED.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Anything below READ COMMITTED breaks the locking design in
 * ReserveStockForOrder and ApplyCouponToOrder (technical-design §4g).
 */
class TransactionIsolationLevelIsSafe extends Check
{
    /** @var array<int, string> */
    private const SAFE_LEVELS = ['REPEATABLE-READ', 'READ-COMMITTED'];

    public function run(): Result
    {
        $result = Result::make();

        if (DB::connection()->getDriverName() !== 'mysql') {
            return $result->ok('Not applicable — the active connection is not MySQL.');
        }

        $row = DB::selectOne('SELECT @@transaction_isolation AS level');
        $level = $row->level ?? null;

        if ($level !== null && in_array($level, self::SAFE_LEVELS, true)) {
            return $result->ok("Transaction isolation level is {$level}.");
        }

        return $result
            ->failed("Transaction isolation level is '{$level}', below READ COMMITTED — the lock-discipline design (ReserveStockForOrder, ApplyCouponToOrder) is not safe at this level. Fix: SET GLOBAL transaction_isolation = 'READ-COMMITTED'; (or REPEATABLE-READ, the InnoDB default).")
            ->meta(['transaction_isolation' => $level]);
    }
}
