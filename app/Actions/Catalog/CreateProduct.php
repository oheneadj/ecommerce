<?php

/**
 * Creates a product together with its initial variant(s).
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\ProductRequiresVariantException;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A product can never be saved without at least one variant, since orders
 * always reference a variant, never a bare product (BRD FR-2.3/E2.3).
 */
class CreateProduct
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $productData
     * @param  array<int, array<string, mixed>>  $variants  each entry: sku, price, stock, status?, low_stock_threshold?,
     *                                                      attributeValues?: array<int, array{attribute_name: string, value: string}>
     *
     * @throws ProductRequiresVariantException when no variants are given
     */
    public function handle(array $productData, array $variants): Product
    {
        if ($variants === []) {
            throw new ProductRequiresVariantException;
        }

        return DB::transaction(function () use ($productData, $variants): Product {
            $product = Product::query()->create($productData);

            foreach ($variants as $variantData) {
                $attributeValues = $variantData['attributeValues'] ?? [];
                unset($variantData['attributeValues']);

                $variant = $product->variants()->create($variantData);

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

            return $product->load('variants.attributeValues');
        });
    }
}
