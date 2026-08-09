<?php

/**
 * Enforces that only one image can be primary within a given scope
 * (a product's general images, or one specific variant's images).
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

/**
 * Runs on every create/update regardless of entry point (the admin panel's
 * Images tab, the per-variant "Add image" action, or any future API), so
 * the invariant holds no matter how an image gets marked primary.
 */
class ProductImageObserver
{
    public function saving(ProductImage $productImage): void
    {
        if (! $productImage->is_primary) {
            return;
        }

        ProductImage::query()
            ->where('product_id', $productImage->product_id)
            ->when(
                $productImage->product_variant_id,
                fn ($query) => $query->where('product_variant_id', $productImage->product_variant_id),
                fn ($query) => $query->whereNull('product_variant_id'),
            )
            ->when($productImage->exists, fn ($query) => $query->where('id', '!=', $productImage->id))
            ->update(['is_primary' => false]);
    }

    /**
     * A safety net for any deletion path that isn't already covered by an
     * explicit `->before()` hook (e.g. a future Action or console command
     * calling `$image->delete()` directly) — `Storage::delete()` on an
     * already-removed file is a harmless no-op, so this is safe to run
     * alongside the admin panel's own explicit cleanup.
     */
    public function deleted(ProductImage $productImage): void
    {
        Storage::disk('public')->delete($productImage->path);
    }
}
