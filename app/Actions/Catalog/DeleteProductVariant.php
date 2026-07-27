<?php

/**
 * Permanently removes a product variant from the active catalog.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Soft-deletes the variant and mutates its SKU to `{sku}-deleted-{id}`,
 * same rule as DeleteProduct — the row's own permanent ID guarantees the
 * original SKU is safe to reuse indefinitely across delete/recreate cycles.
 * Wrapped in a transaction so the SKU mutation and the soft delete are
 * atomic — same reasoning as DeleteProduct.
 */
class DeleteProductVariant
{
    use AsAction;

    /**
     * @return bool whether the parent product was auto-downgraded to Draft
     *              (it had no variants left and was Active)
     */
    public function handle(ProductVariant $variant): bool
    {
        return DB::transaction(function () use ($variant): bool {
            $product = $variant->product;

            $variant->update(['sku' => "{$variant->sku}-deleted-{$variant->id}"]);
            $variant->delete();

            if ($product->status === ProductStatus::Active && $product->variants()->doesntExist()) {
                $product->update(['status' => ProductStatus::Draft]);

                return true;
            }

            return false;
        });
    }
}
