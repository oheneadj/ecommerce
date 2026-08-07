<?php

/**
 * Applies a percentage change to a variant's price.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\ProductVariant;
use Lorisleiva\Actions\Concerns\AsAction;

class AdjustVariantPrice
{
    use AsAction;

    public function handle(ProductVariant $variant, float $percentage): ProductVariant
    {
        $newPrice = (int) round($variant->price * (1 + $percentage / 100));

        $variant->update(['price' => max(0, $newPrice)]);

        return $variant;
    }
}
