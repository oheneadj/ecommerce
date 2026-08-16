<?php

/**
 * Production remediation for a StockCacheMatchesMovements (System Health,
 * Tier 3) failure — backfills the missing ledger entries for whichever
 * variants have drifted.
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `product_variants.stock` is a denormalised cache of
 * `SUM(stock_movements.quantity)` — every legitimate write is supposed to
 * go through RecordStockMovement, which updates both together. A write
 * path that bypasses it (a bug, a raw query, a manual DB edit) leaves
 * `stock` correct but the ledger short, which is exactly what this
 * command assumes: it trusts the cached `stock` value as correct and
 * inserts the ledger entry that's missing to match it — it never adjusts
 * `stock` itself. This is why it writes to `stock_movements` directly
 * instead of going through RecordStockMovement: that Action always
 * increments `stock` alongside the insert, which would double-count an
 * already-correct value.
 *
 * Dry-run by default — always run without --force first and review the
 * table before trusting it with a write.
 */
class ReconcileStockCacheCommand extends Command
{
    protected $signature = 'health:reconcile-stock-cache {--force : Actually write the backfilling stock_movements rows (default is dry-run)}';

    protected $description = 'Backfill missing stock_movements rows for variants where stock has drifted from the ledger (StockCacheMatchesMovements remediation).';

    /**
     * Finds every variant whose `stock` disagrees with its movement sum
     * (same query the health check itself runs), shows what a backfill
     * would write, and only actually writes it once `--force` is passed
     * and the operator confirms — never on a bare run.
     */
    public function handle(): int
    {
        $mismatches = DB::table('product_variants')
            ->leftJoin('stock_movements', 'stock_movements.product_variant_id', '=', 'product_variants.id')
            ->groupBy('product_variants.id', 'product_variants.sku', 'product_variants.stock')
            ->havingRaw('product_variants.stock != COALESCE(SUM(stock_movements.quantity), 0)')
            ->selectRaw('product_variants.id, product_variants.sku, product_variants.stock, COALESCE(SUM(stock_movements.quantity), 0) as movement_sum')
            ->get();

        if ($mismatches->isEmpty()) {
            $this->info('No mismatches found — stock cache already matches the ledger for every variant.');

            return self::SUCCESS;
        }

        $this->table(
            ['Variant ID', 'SKU', 'Cached stock', 'Ledger sum', 'Backfill quantity'],
            $mismatches->map(fn (object $row): array => [
                $row->id,
                $row->sku,
                $row->stock,
                $row->movement_sum,
                $row->stock - $row->movement_sum,
            ]),
        );

        if (! $this->option('force')) {
            $this->warn("{$mismatches->count()} variant(s) affected. This was a dry run — no changes were made. Re-run with --force to write the backfilling movements above.");

            return self::SUCCESS;
        }

        if (! $this->confirm("About to insert {$mismatches->count()} backfilling stock_movements row(s), as shown above. product_variants.stock is not touched. Continue?")) {
            $this->info('Aborted — no changes were made.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($mismatches): void {
            foreach ($mismatches as $row) {
                StockMovement::query()->create([
                    'product_variant_id' => $row->id,
                    'type' => StockMovementType::Adjustment,
                    'quantity' => $row->stock - $row->movement_sum,
                    'note' => 'Backfill: reconciling stock cache to ledger (health check remediation, health:reconcile-stock-cache).',
                ]);
            }
        });

        $this->info("Backfilled {$mismatches->count()} variant(s). Re-run `php artisan health:run-integrity-checks` to confirm the health check now passes.");

        return self::SUCCESS;
    }
}
