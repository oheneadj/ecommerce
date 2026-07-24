<?php

/**
 * Computes a cart's current subtotal from live variant prices.
 */

declare(strict_types=1);

namespace App\Queries;

use App\Models\Cart;

/**
 * Read-only — the cart never locks in a price, so its total is always
 * computed from each item's variant's *current* price, never a cached
 * value (BRD Principle 8). Not an Action: no side effects.
 */
class CartTotalsQuery
{
    public function __invoke(Cart $cart): int
    {
        return $cart->items()
            ->with('productVariant')
            ->get()
            ->sum(fn ($item) => $item->productVariant->price * $item->quantity);
    }
}
