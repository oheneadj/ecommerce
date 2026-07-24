<?php

/**
 * Retrieves a user's past orders, most recent first.
 */

declare(strict_types=1);

namespace App\Queries;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only — no side effects. Order display must always read from
 * OrderItem's own `item_snapshot`, never live Product/ProductVariant data,
 * so eager-loading here deliberately stops at `items` (no further nested
 * `productVariant.product` load, since that data is never used for display).
 */
class OrderHistoryQuery
{
    /**
     * @return Collection<int, Order>
     */
    public function __invoke(User $user): Collection
    {
        return $user->orders()
            ->with('items', 'statusHistories')
            ->latest()
            ->get();
    }
}
