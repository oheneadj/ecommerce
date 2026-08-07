<?php

/**
 * Assigns a human-readable order number before an Order is inserted.
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;

/**
 * Format: ORD-{year}-{zero-padded sequence}, e.g. ORD-2026-000123 — the
 * sequence resets to 1 at the start of each year, scoped by counting only
 * that year's orders (a global all-time count would make the year
 * component decorative rather than meaningful, and grow more expensive
 * with every order the store has ever taken). The `order_number` column's
 * unique constraint is the actual safety net for the rare case of two
 * orders computing the same sequence concurrently — this value is a
 * human-facing reference, not a correctness-critical identifier, so it
 * doesn't warrant the row-locking reserved for stock and coupon usage
 * (AGENTS.md Section 4a).
 */
class OrderObserver
{
    public function creating(Order $order): void
    {
        $year = now()->format('Y');

        $order->order_number ??= sprintf(
            'ORD-%s-%06d',
            $year,
            Order::query()->whereYear('created_at', $year)->count() + 1,
        );
    }
}
