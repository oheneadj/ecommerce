<?php

/**
 * Permanently removes a product variant from the active catalog.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ProductVariant;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Soft-deletes the variant and mutates its SKU to `{sku}-deleted-{id}`,
 * same rule as DeleteProduct — the row's own permanent ID guarantees the
 * original SKU is safe to reuse indefinitely across delete/recreate cycles.
 */
class DeleteProductVariant
{
    use AsAction;

    public function handle(ProductVariant $variant): void
    {
        $variant->update(['sku' => "{$variant->sku}-deleted-{$variant->id}"]);

        $variant->delete();
    }
}
