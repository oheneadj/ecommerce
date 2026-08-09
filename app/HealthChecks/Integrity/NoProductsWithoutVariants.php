<?php

/**
 * Tier 3 (data integrity) — WARNING: no non-archived product has zero variants.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

use App\Enums\ProductStatus;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * `CreateProduct` throws `ProductRequiresVariantException` before letting
 * an Active product exist with no variants, but a variant can be deleted
 * afterward — Archived products are exempt since "stopped selling" is a
 * legitimate reason to have none left (technical-design §4g).
 */
class NoProductsWithoutVariants implements IntegrityCheck
{
    public function name(): string
    {
        return 'No products without variants';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function remediationHint(): string
    {
        return 'This product has no sellable variant left but is not Archived — either add a variant or archive it via the admin panel.';
    }

    public function run(): IntegrityCheckOutcome
    {
        $ids = DB::table('products')
            ->leftJoin('product_variants', function (JoinClause $join) {
                $join->on('product_variants.product_id', '=', 'products.id')
                    ->whereNull('product_variants.deleted_at');
            })
            ->whereNull('products.deleted_at')
            ->where('products.status', '!=', ProductStatus::Archived->value)
            ->whereNull('product_variants.id')
            ->pluck('products.id')
            ->all();

        return $ids === [] ? IntegrityCheckOutcome::clean() : IntegrityCheckOutcome::violations($ids);
    }
}
