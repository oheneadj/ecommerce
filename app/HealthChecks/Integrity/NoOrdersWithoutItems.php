<?php

/**
 * Tier 3 (data integrity) — CRITICAL: no order has zero order_items.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

use Illuminate\Support\Facades\DB;

/**
 * `CreateOrderFromCart` throws `EmptyCartException` before creating an
 * order with no items — an order with zero items in the DB means that
 * guarantee was bypassed, or the items were deleted afterward.
 */
class NoOrdersWithoutItems implements IntegrityCheck
{
    public function name(): string
    {
        return 'No orders without items';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function remediationHint(): string
    {
        return 'An order with no line items is a financial record with nothing to fulfill or reconcile — investigate whether CreateOrderFromCart was bypassed or an order_items row was deleted directly.';
    }

    public function run(): IntegrityCheckOutcome
    {
        $ids = DB::table('orders')
            ->leftJoin('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereNull('order_items.id')
            ->pluck('orders.id')
            ->all();

        return $ids === [] ? IntegrityCheckOutcome::clean() : IntegrityCheckOutcome::violations($ids);
    }
}
