<?php

/**
 * Tier 3 (data integrity) — WARNING: every soft-deleted product/variant's
 * slug/sku was actually mutated, proving the delete transaction worked.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

use Illuminate\Support\Facades\DB;

/**
 * `DeleteProduct`/`DeleteProductVariant` mutate `slug`/`sku` to
 * `{original}-deleted-{id}` in the same transaction as the soft delete, so
 * the original value is immediately free for a new record to reuse. A
 * soft-deleted row whose slug/sku doesn't end that way means the mutation
 * half of that transaction never happened — proof the delete didn't run
 * as designed, even though the row shows as deleted.
 */
class NoSoftDeletedRecordHoldsOriginalUniqueValue implements IntegrityCheck
{
    public function name(): string
    {
        return 'No soft-deleted record holds its original unique value';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function remediationHint(): string
    {
        return 'A soft-deleted product/variant still holds its original slug/sku — DeleteProduct/DeleteProductVariant were bypassed. Manually rename it to `{value}-deleted-{id}` to free the value for reuse.';
    }

    public function run(): IntegrityCheckOutcome
    {
        $productIds = $this->idsWithUnmutatedValue('products', 'slug');
        $variantIds = $this->idsWithUnmutatedValue('product_variants', 'sku');

        $allIds = [...$productIds, ...$variantIds];

        return $allIds === [] ? IntegrityCheckOutcome::clean() : IntegrityCheckOutcome::violations($allIds);
    }

    /**
     * Fetched and filtered in PHP rather than a raw SQL LIKE pattern —
     * soft-deleted rows are a small fraction of the table, and `CONCAT`
     * isn't portable across every database driver this app might run on.
     *
     * @return array<int, int>
     */
    private function idsWithUnmutatedValue(string $table, string $column): array
    {
        return DB::table($table)
            ->whereNotNull('deleted_at')
            ->get(['id', $column])
            ->reject(fn ($row) => str_ends_with((string) $row->{$column}, "-deleted-{$row->id}"))
            ->pluck('id')
            ->all();
    }
}
