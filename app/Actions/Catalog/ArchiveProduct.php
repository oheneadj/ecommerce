<?php

/**
 * Discontinues a product without deleting it.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Stop selling this, might bring it back" — no slug change, no soft-delete.
 * Distinct from DeleteProduct, which permanently removes it (BRD Section 3.1 note).
 */
class ArchiveProduct
{
    use AsAction;

    public function handle(Product $product): Product
    {
        $product->update(['status' => ProductStatus::Archived]);

        return $product;
    }
}
