<?php

/**
 * Permanently removes a product from the active catalog.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Soft-deletes the product and mutates its slug to `{slug}-deleted-{id}`,
 * using the row's own permanent database ID — always unique — so the
 * original slug is immediately free for a brand new product to reuse, with
 * no collision risk even after repeated create/delete/recreate cycles.
 * Never a hard delete: existing OrderItem/StockMovement history must stay valid.
 * Wrapped in a transaction so the slug mutation and the soft delete are
 * atomic — a rename that succeeds without the delete would silently and
 * permanently block the original slug from ever being reused.
 */
class DeleteProduct
{
    use AsAction;

    public function handle(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->update(['slug' => "{$product->slug}-deleted-{$product->id}"]);

            $product->delete();
        });
    }
}
