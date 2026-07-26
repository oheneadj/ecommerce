<?php

/**
 * Computes the admin dashboard's summary metrics (BRD FR-10.5).
 */

declare(strict_types=1);

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VariantStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only — no side effects. "Sales"/"revenue" are counted from
 * successful `Payment` rows (money actually received), not order totals
 * (an order can exist without ever being paid). "Verified" order statuses
 * mirror SubmitReview's own definition of a completed purchase.
 */
class DashboardMetricsQuery
{
    private const VERIFIED_ORDER_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Processing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
    ];

    public function todaysSales(): int
    {
        return (int) Payment::query()
            ->where('status', PaymentStatus::Success)
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');
    }

    public function monthlyRevenue(): int
    {
        return (int) Payment::query()
            ->where('status', PaymentStatus::Success)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');
    }

    public function pendingOrdersCount(): int
    {
        return Order::query()->where('status', OrderStatus::Pending)->count();
    }

    public function lowStockCount(): int
    {
        return ProductVariant::query()
            ->where('status', VariantStatus::Active)
            ->lowStock()
            ->count();
    }

    public function newCustomersCount(): int
    {
        return User::query()
            ->whereDoesntHave('roles')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }

    /**
     * @return Collection<int, array{product_id: int, product_name: string, quantity_sold: int}>
     */
    public function topProducts(int $limit = 5): Collection
    {
        $statuses = array_map(fn (OrderStatus $status) => $status->value, self::VERIFIED_ORDER_STATUSES);

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('orders.status', $statuses)
            ->whereYear('orders.created_at', now()->year)
            ->whereMonth('orders.created_at', now()->month)
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->select(['products.id as product_id', 'products.name as product_name', DB::raw('SUM(order_items.quantity) as quantity_sold')])
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'quantity_sold' => (int) $row->quantity_sold,
            ]);
    }
}
