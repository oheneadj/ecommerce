<?php

/**
 * Describes which products/variants are blocking an attribute's deletion,
 * by name/SKU rather than just a count — so the block message tells an
 * admin exactly what to go unassign instead of leaving them to hunt for it.
 */

declare(strict_types=1);

namespace App\Support;

use App\Models\Attribute;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class AttributeUsageSummary
{
    private const int LIST_LIMIT = 5;

    /**
     * @param  iterable<int|string, Attribute>  $attributes
     */
    public static function forBlockedDelete(iterable $attributes): ?string
    {
        $attributes = collect($attributes);
        $attributeIds = $attributes->pluck('id');

        $productNames = $attributes->flatMap(fn (Attribute $attribute): Collection => $attribute->products()->pluck('name'))
            ->unique()
            ->values();

        $variantSkus = ProductVariant::query()
            ->whereHas('attributeTerms', fn ($query) => $query->whereIn('attribute_id', $attributeIds))
            ->pluck('sku');

        if ($productNames->isEmpty() && $variantSkus->isEmpty()) {
            return null;
        }

        $noun = $attributes->count() > 1 ? 'These attributes are' : 'This attribute is';

        $parts = array_filter([
            $productNames->isNotEmpty() ? 'products: '.self::describe($productNames) : null,
            $variantSkus->isNotEmpty() ? 'variants: '.self::describe($variantSkus) : null,
        ]);

        return "{$noun} still assigned on ".implode('; ', $parts).'. Remove it from those first.';
    }

    /**
     * @param  Collection<int, string>  $names
     */
    private static function describe(Collection $names): string
    {
        $shown = $names->take(self::LIST_LIMIT)->implode(', ');
        $remaining = $names->count() - self::LIST_LIMIT;

        return $remaining > 0 ? "{$shown}, and {$remaining} more" : $shown;
    }
}
