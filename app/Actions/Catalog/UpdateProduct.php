<?php

/**
 * Updates a product's own fields (never its variants — those are managed
 * separately via the Variants tab).
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Exceptions\ProductRequiresVariantException;
use App\Models\Product;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Same rule as CreateProduct: a product can't be set Active while it has
 * zero variants. Draft products aren't checked at all — only a transition
 * to (or staying at) Active is gated.
 */
class UpdateProduct
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ProductRequiresVariantException when the resulting status is Active and the product has no variants
     */
    public function handle(Product $product, array $data): Product
    {
        $status = $data['status'] ?? $product->status;
        $status = $status instanceof ProductStatus ? $status : ProductStatus::from($status);

        if ($status === ProductStatus::Active && $product->variants()->doesntExist()) {
            throw new ProductRequiresVariantException;
        }

        $product->update($data);

        return $product;
    }
}
