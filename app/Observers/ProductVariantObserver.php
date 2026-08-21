<?php

/**
 * Frees a soft-deleted variant's SKU for reuse — without this, the SKU
 * stays reserved forever under the table's unique constraint even though
 * the variant is no longer active, permanently blocking a new variant
 * (on this product or any other) from ever using that value again.
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\ProductVariant;

class ProductVariantObserver
{
    public function deleting(ProductVariant $variant): void
    {
        $variant->forceFill(['sku' => "{$variant->sku}-deleted-{$variant->id}"])->saveQuietly();
    }
}
