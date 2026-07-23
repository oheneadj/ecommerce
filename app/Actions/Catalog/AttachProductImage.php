<?php

/**
 * Attaches an uploaded image to a product or a specific variant.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Records display order and primary-image status; a variant-specific image
 * links both to the product and the variant, a general product image only
 * to the product.
 */
class AttachProductImage
{
    use AsAction;

    public function handle(
        Product $product,
        string $path,
        ?ProductVariant $variant = null,
        int $sortOrder = 0,
        bool $isPrimary = false,
    ): ProductImage {
        return $product->images()->create([
            'product_variant_id' => $variant?->id,
            'path' => $path,
            'sort_order' => $sortOrder,
            'is_primary' => $isPrimary,
        ]);
    }
}
