<?php

/**
 * Bulk-generates every combination across a set of attributes (e.g. Size ×
 * Color) as separate variants on a product, instead of adding them one at a
 * time by hand.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Given e.g. `['Size' => ['L', 'M', 'XL'], 'Color' => ['White', 'Blue', 'Black']]`,
 * creates one variant per combination (9 here), each carrying the matching
 * AttributeValue rows. Any combination that already exists on the product
 * (same attribute name/value pairs, regardless of order) is skipped rather
 * than duplicated — this is the only thing standing between a product and
 * two variants both claiming to be "Size: XL, Color: White".
 */
class GenerateProductVariants
{
    use AsAction;

    /**
     * @param  array<string, array<int, string>>  $attributeGroups  e.g. ['Size' => ['L', 'M'], 'Color' => ['Red']]
     * @return Collection<int, ProductVariant>
     */
    public function handle(
        Product $product,
        array $attributeGroups,
        int $defaultPrice,
        int $defaultStock,
        string $skuPrefix,
    ): Collection {
        $combinations = $this->cartesianProduct($attributeGroups);
        $existingCombinations = $this->existingAttributeCombinations($product);

        return DB::transaction(function () use ($product, $combinations, $existingCombinations, $defaultPrice, $defaultStock, $skuPrefix): Collection {
            $created = collect();

            foreach ($combinations as $combination) {
                if ($existingCombinations->contains($this->combinationKey($combination))) {
                    continue;
                }

                $variant = $product->variants()->create([
                    'sku' => $this->buildSku($skuPrefix, $combination),
                    'price' => $defaultPrice,
                    'stock' => $defaultStock,
                    'status' => VariantStatus::Active,
                ]);

                foreach ($combination as $attributeName => $value) {
                    $variant->attributeValues()->create([
                        'attribute_name' => $attributeName,
                        'value' => $value,
                    ]);
                }

                $created->push($variant);
            }

            return $created;
        });
    }

    /**
     * @param  array<string, array<int, string>>  $attributeGroups
     * @return array<int, array<string, string>>
     */
    private function cartesianProduct(array $attributeGroups): array
    {
        $combinations = [[]];

        foreach ($attributeGroups as $attributeName => $values) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $next[] = [...$combination, $attributeName => $value];
                }
            }

            $combinations = $next;
        }

        return $combinations;
    }

    /**
     * @return Collection<int, string>
     */
    private function existingAttributeCombinations(Product $product): Collection
    {
        return $product->variants()
            ->with('attributeValues')
            ->get()
            ->map(fn (ProductVariant $variant): string => $this->combinationKey(
                $variant->attributeValues->pluck('value', 'attribute_name')->all(),
            ));
    }

    /**
     * @param  array<string, string>  $combination
     */
    private function combinationKey(array $combination): string
    {
        ksort($combination);

        return collect($combination)->map(fn (string $value, string $name): string => "{$name}:{$value}")->implode('|');
    }

    /**
     * @param  array<string, string>  $combination
     */
    private function buildSku(string $skuPrefix, array $combination): string
    {
        $suffix = collect($combination)->map(fn (string $value): string => str($value)->slug()->upper()->toString())->implode('-');

        return str($skuPrefix)->slug()->upper()->toString()."-{$suffix}";
    }
}
