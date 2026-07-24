<?php

/**
 * Logs a single stock change and updates the variant's cached stock total.
 */

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The single write path for `stock_movements` — every Action that changes a
 * variant's available stock must go through this one (never insert into
 * stock_movements directly), so `product_variants.stock` and its movement
 * history can never drift apart.
 */
class RecordStockMovement
{
    use AsAction;

    public function handle(
        ProductVariant $variant,
        StockMovementType $type,
        int $quantity,
        ?User $user = null,
        ?string $note = null,
        ?Model $reference = null,
    ): StockMovement {
        $movement = StockMovement::query()->create([
            'product_variant_id' => $variant->id,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'user_id' => $user?->id,
        ]);

        $variant->increment('stock', $quantity);

        return $movement;
    }
}
