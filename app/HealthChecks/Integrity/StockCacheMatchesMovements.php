<?php

/**
 * Tier 3 (data integrity) — CRITICAL: product_variants.stock must equal the
 * sum of its stock_movements.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

use Illuminate\Support\Facades\DB;

/**
 * Deliberately denormalised cache for read performance (technical-design
 * §4g) — nothing in the schema enforces this, only RecordStockMovement's
 * `$variant->increment('stock', $quantity)` alongside every insert. Any
 * write path that bypasses it (a bug, a raw query, a manual DB edit)
 * drifts silently with no error anywhere.
 */
class StockCacheMatchesMovements implements IntegrityCheck
{
    public function name(): string
    {
        return 'Stock cache matches movements';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function remediationHint(): string
    {
        return 'Recompute product_variants.stock from SUM(stock_movements.quantity) for each affected variant, then find which write path bypassed RecordStockMovement.';
    }

    public function run(): IntegrityCheckOutcome
    {
        $mismatchedIds = DB::table('product_variants')
            ->leftJoin('stock_movements', 'stock_movements.product_variant_id', '=', 'product_variants.id')
            ->groupBy('product_variants.id', 'product_variants.stock')
            ->havingRaw('product_variants.stock != COALESCE(SUM(stock_movements.quantity), 0)')
            ->pluck('product_variants.id')
            ->all();

        return $mismatchedIds === [] ? IntegrityCheckOutcome::clean() : IntegrityCheckOutcome::violations($mismatchedIds);
    }
}
