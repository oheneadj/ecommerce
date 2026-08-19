<?php

/**
 * Reserves a quantity of a variant's stock for a limited window during checkout.
 */

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\StockReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidReservationQuantityException;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One of only two Actions in the whole system that requires row-level
 * locking (the other is ApplyCouponToOrder) — stock is a finite, contested
 * resource, so the availability check and the reservation insert must
 * happen inside the same locked transaction. Checking outside the lock and
 * inserting inside it would defeat the mechanism entirely (BRD FR-2.5/FR-2.8).
 *
 * The lock is taken on the variant row, not on `stock_reservations` — every
 * writer affecting a variant's available stock must take this same lock
 * first, or serialization silently breaks for every other caller.
 */
class ReserveStockForOrder
{
    use AsAction;

    /**
     * @throws InsufficientStockException when available stock can't cover the request
     * @throws InvalidReservationQuantityException when $quantity is zero or negative
     */
    public function handle(ProductVariant $variant, int $quantity, Order $order): StockReservation
    {
        if ($quantity <= 0) {
            throw new InvalidReservationQuantityException;
        }

        return DB::transaction(function () use ($variant, $quantity, $order): StockReservation {
            $locked = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();

            $reserved = StockReservation::query()
                ->where('product_variant_id', $locked->id)
                ->where('status', StockReservationStatus::Active)
                ->sum('quantity');

            if (($locked->stock - $reserved) < $quantity) {
                throw new InsufficientStockException;
            }

            return StockReservation::query()->create([
                'product_variant_id' => $locked->id,
                'order_id' => $order->id,
                'quantity' => $quantity,
                'status' => StockReservationStatus::Active,
                'expires_at' => now()->addMinutes(StoreSetting::current()->stock_reservation_minutes),
            ]);
        }, 3);
    }
}
