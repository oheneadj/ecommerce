<?php

/**
 * Assigns a human-readable order number before an Order is inserted.
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;

/**
 * Format: ORD-{year}-{zero-padded sequence}, e.g. ORD-2026-000123. The
 * `order_number` column's unique constraint is the actual safety net for
 * the rare case of two orders computing the same sequence concurrently —
 * this value is a human-facing reference, not a correctness-critical
 * identifier, so it doesn't warrant the row-locking reserved for stock and
 * coupon usage (AGENTS.md Section 4a).
 */
class OrderObserver
{
    public function creating(Order $order): void
    {
        $order->order_number ??= sprintf(
            'ORD-%s-%06d',
            now()->format('Y'),
            Order::query()->count() + 1,
        );
    }
}
