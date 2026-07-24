<?php

/**
 * Releases stock reservations whose checkout window has expired.
 */

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Scheduled every 1–5 minutes (BRD FR-2.6). An abandoned checkout must not
 * hold stock forever — releasing just flips the reservation's status, since
 * the variant's available stock is always computed as `stock - sum(active
 * reservations)`, never a separately decremented column.
 */
class ReleaseExpiredReservations
{
    use AsAction;

    public function handle(): int
    {
        return DB::transaction(fn (): int => StockReservation::query()
            ->where('status', StockReservationStatus::Active)
            ->where('expires_at', '<', now())
            ->update(['status' => StockReservationStatus::Released]));
    }
}
