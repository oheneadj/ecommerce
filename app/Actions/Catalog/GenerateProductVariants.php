<?php

/**
 * Bulk-generates every combination across a set of global attribute terms
 * (e.g. Size × Color) as separate variants on a product, instead of adding
 * them one at a time by hand.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Enums\VariantStatus;
use App\Exceptions\ProductVariantLimitExceededException;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Given e.g. `[[sizeIdL, sizeIdM], [colorIdWhite, colorIdBlack]]` (one array
 * of AttributeTerm IDs per attribute), creates one variant per combination
 * (4 here), each linked to the matching AttributeTerm rows via the
 * attributeTerms() pivot. Any combination that already exists on the
 * product (same set of term IDs, regardless of order) is skipped rather
 * than duplicated.
 *
 * @throws ProductVariantLimitExceededException when the resulting total (existing + newly generated) would exceed config('media.product_max_variants')
 */
class GenerateProductVariants
{
    use AsAction;

    /**
     * @param  array<int, array<int, int>>  $termGroups  e.g. [[1, 2], [5]] — each inner array is one attribute's selected term IDs
     * @return Collection<int, ProductVariant>
     */
    public function handle(
        Product $product,
        array $termGroups,
        int $defaultPrice,
        int $defaultStock,
        string $skuPrefix,
        ?User $actor = null,
    ): Collection {
        $termGroups = array_values(array_filter($termGroups, fn (array $group): bool => $group !== []));
        $combinations = $this->cartesianProduct($termGroups);
        $existingCombinations = $this->existingTermCombinations($product);

        // Only combinations that would actually create a new variant count
        // toward the cap — re-running "Generate variants" over an already-
        // covered combination is a no-op, not new growth.
        $newCombinationCount = collect($combinations)
            ->reject(fn (array $termIds): bool => $existingCombinations->contains($this->combinationKey($termIds)))
            ->count();

        $limit = (int) config('media.product_max_variants');

        if ($existingCombinations->count() + $newCombinationCount > $limit) {
            throw new ProductVariantLimitExceededException($limit);
        }

        $termsById = AttributeTerm::query()
            ->whereIn('id', $termGroups === [] ? [] : array_merge(...$termGroups))
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($product, $combinations, $existingCombinations, $defaultPrice, $defaultStock, $skuPrefix, $termsById, $actor): Collection {
            $created = collect();

            foreach ($combinations as $termIds) {
                if ($existingCombinations->contains($this->combinationKey($termIds))) {
                    continue;
                }

                $terms = collect($termIds)->map(fn (int $id): AttributeTerm => $termsById[$id]);

                // Created with `stock` at 0, not $defaultStock — the initial
                // count is applied through RecordStockMovement below so it's
                // backed by a real ledger entry, same as every other write
                // to a variant's stock (technical-design-ecommerce.md's
                // "never update stock directly" rule applies to a brand-new
                // variant too, not just an existing one).
                $variant = $product->variants()->create([
                    'sku' => $this->buildSku($skuPrefix, $terms),
                    'price' => $defaultPrice,
                    'stock' => 0,
                    'status' => VariantStatus::Active,
                ]);

                $variant->attributeTerms()->sync($termIds);

                if ($defaultStock > 0) {
                    RecordStockMovement::run($variant, StockMovementType::Restock, $defaultStock, $actor, 'Initial stock at creation');
                }

                $created->push($variant);
            }

            return $created;
        });
    }

    /**
     * @param  array<int, array<int, int>>  $termGroups
     * @return array<int, array<int, int>>
     */
    private function cartesianProduct(array $termGroups): array
    {
        $combinations = [[]];

        foreach ($termGroups as $termIds) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($termIds as $termId) {
                    $next[] = [...$combination, $termId];
                }
            }

            $combinations = $next;
        }

        return $combinations;
    }

    /**
     * @return Collection<int, string>
     */
    private function existingTermCombinations(Product $product): Collection
    {
        return $product->variants()
            ->with('attributeTerms')
            ->get()
            ->map(fn (ProductVariant $variant): string => $this->combinationKey(
                $variant->attributeTerms->pluck('id')->all(),
            ));
    }

    /**
     * @param  array<int, int>  $termIds
     */
    private function combinationKey(array $termIds): string
    {
        sort($termIds);

        return implode('|', $termIds);
    }

    /**
     * @param  Collection<int, AttributeTerm>  $terms
     */
    private function buildSku(string $skuPrefix, Collection $terms): string
    {
        $suffix = $terms->map(fn (AttributeTerm $term): string => str($term->value)->slug()->upper()->toString())->implode('-');

        return str($skuPrefix)->slug()->upper()->toString()."-{$suffix}";
    }
}
