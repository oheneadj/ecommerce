<?php

/**
 * Permanently removes a product from the active catalog.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Product;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Soft-deletes the product and mutates its slug to `{slug}-deleted-{id}`,
 * using the row's own permanent database ID — always unique — so the
 * original slug is immediately free for a brand new product to reuse, with
 * no collision risk even after repeated create/delete/recreate cycles.
 * Never a hard delete: existing OrderItem/StockMovement history must stay valid.
 */
class DeleteProduct
{
    use AsAction;

    public function handle(Product $product): void
    {
        $product->update(['slug' => "{$product->slug}-deleted-{$product->id}"]);

        $product->delete();
    }
}
