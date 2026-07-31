<?php

/**
 * Creates a product together with its initial variant(s).
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Exceptions\ProductRequiresVariantException;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A Draft product can be created with zero variants (a work in progress,
 * never customer-facing) — an Active one can't, since orders always
 * reference a variant, never a bare product.
 */
class CreateProduct
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $productData
     * @param  array<int, array<string, mixed>>  $variants  each entry: sku, price, stock, status?, low_stock_threshold?,
     *                                                      attribute_term_ids?: array<int, int> (global attribute values),
     *                                                      attributeValues?: array<int, array{attribute_name: string, value: string}> (custom/local values)
     *
     * @throws ProductRequiresVariantException when creating with status Active and no variants
     */
    public function handle(array $productData, array $variants): Product
    {
        $status = $productData['status'] ?? ProductStatus::Draft;
        $status = $status instanceof ProductStatus ? $status : ProductStatus::from($status);

        if ($status === ProductStatus::Active && $variants === []) {
            throw new ProductRequiresVariantException;
        }

        return DB::transaction(function () use ($productData, $variants): Product {
            $product = Product::query()->create($productData);

            foreach ($variants as $variantData) {
                $attributeValues = $variantData['attributeValues'] ?? [];
                $attributeTermIds = $variantData['attribute_term_ids'] ?? [];
                unset($variantData['attributeValues'], $variantData['attribute_term_ids']);

                $variant = $product->variants()->create($variantData);

                if ($attributeTermIds !== []) {
                    $variant->attributeTerms()->sync($attributeTermIds);
                }

                foreach ($attributeValues as $attributeValue) {
                    if (blank($attributeValue['attribute_name'] ?? null) || blank($attributeValue['value'] ?? null)) {
                        continue;
                    }

                    $variant->attributeValues()->create([
                        'attribute_name' => $attributeValue['attribute_name'],
                        'value' => $attributeValue['value'],
                    ]);
                }
            }

            return $product->load('variants.attributeValues', 'variants.attributeTerms');
        });
    }
}
