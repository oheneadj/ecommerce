<?php

/**
 * Removes the stored files for a product's images from disk — used before
 * a force-delete, since the `product_images` rows disappear via a DB-level
 * cascade (ProductImage's own Eloquent `deleting` event never fires for
 * that), which would otherwise leave the files orphaned in storage.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteProductImageFiles
{
    use AsAction;

    public function handle(Product $product): void
    {
        $paths = $product->images()->pluck('path');

        if ($paths->isEmpty()) {
            return;
        }

        Storage::disk('public')->delete($paths->all());
    }
}
