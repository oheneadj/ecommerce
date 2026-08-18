<?php

/**
 * Logs a single stock change and updates the variant's cached stock total.
 */

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Exceptions\InvalidStockMovementQuantityException;
use App\Exceptions\NegativeStockException;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Notifications\Support\SafeNotifier;
use App\Notifications\Support\StaffRecipients;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The single write path for `stock_movements` — every Action that changes a
 * variant's available stock must go through this one (never insert into
 * stock_movements directly), so `product_variants.stock` and its movement
 * history can never drift apart.
 *
 * Checks for a low-stock crossing on every movement (in addition to the
 * daily CheckLowStockLevels sweep) — alerts only fire the moment stock
 * crosses from above its threshold to at-or-below it, not on every
 * subsequent sale while already low, to avoid spamming Store Keeper.
 *
 * @throws InvalidStockMovementQuantityException when quantity is zero
 * @throws NegativeStockException when the movement would leave stock below zero
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
        if ($quantity === 0) {
            throw new InvalidStockMovementQuantityException;
        }

        $stockBefore = $variant->stock;

        if ($stockBefore + $quantity < 0) {
            throw new NegativeStockException;
        }

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

        if ($quantity < 0 && $stockBefore > $variant->effectiveLowStockThreshold() && $variant->isLowStock()) {
            DB::afterCommit(fn () => SafeNotifier::send(
                StaffRecipients::forRole(UserRole::StoreKeeper->value),
                new LowStockAlert($variant),
            ));
        }

        return $movement;
    }
}
