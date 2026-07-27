<?php

/**
 * Computes the admin dashboard's summary metrics (BRD FR-10.5).
 */

declare(strict_types=1);

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\StockMovementType;
use App\Enums\VariantStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only — no side effects. "Sales"/"revenue" are counted from
 * successful `Payment` rows (money actually received) net of successful
 * `Refund` rows issued in the same window — not order totals (an order can
 * exist without ever being paid), and never just gross payments, since
 * `Payment::$status` stays `Success` forever even after a full refund
 * (there's no `Refunded` payment status). "Verified" order statuses mirror
 * SubmitReview's own definition of a completed purchase.
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
        return $this->netRevenue(fn (Builder $query): Builder => $query->whereDate('created_at', now()->toDateString()));
    }

    public function monthlyRevenue(): int
    {
        return $this->revenueForMonth(now());
    }

    /**
     * Net revenue (successful payments minus successful refunds) for an
     * arbitrary calendar month — used by monthlyRevenue() for the current
     * month and by MonthlyRevenueChart for each of the last 6.
     */
    public function revenueForMonth(DateTimeInterface $month): int
    {
        return $this->netRevenue(fn (Builder $query): Builder => $query
            ->whereYear('created_at', $month->format('Y'))
            ->whereMonth('created_at', $month->format('n')));
    }

    /**
     * Successful payments minus successful refunds, both scoped by the same
     * date window — a refund reduces the revenue of the period it was
     * issued in, not the period the original payment happened in.
     */
    private function netRevenue(Closure $dateScope): int
    {
        $payments = $dateScope(Payment::query()->where('status', PaymentStatus::Success))->sum('amount');
        $refunds = $dateScope(Refund::query()->where('status', RefundStatus::Success))->sum('amount');

        return (int) $payments - (int) $refunds;
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
     * Ranked by quantity sold minus quantity returned via a refund in the
     * same window — a fully-refunded order's items would otherwise still
     * count as "sold" forever, since a refund never changes the order's own
     * status.
     *
     * @return Collection<int, array{product_id: int, product_name: string, quantity_sold: int}>
     */
    public function topProducts(int $limit = 5): Collection
    {
        $statuses = array_map(fn (OrderStatus $status) => $status->value, self::VERIFIED_ORDER_STATUSES);

        $sold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('orders.status', $statuses)
            ->whereYear('orders.created_at', now()->year)
            ->whereMonth('orders.created_at', now()->month)
            ->groupBy('products.id', 'products.name')
            ->select(['products.id as product_id', 'products.name as product_name', DB::raw('SUM(order_items.quantity) as quantity_sold')])
            ->get();

        $returned = DB::table('stock_movements')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->where('stock_movements.type', StockMovementType::Return->value)
            ->where('stock_movements.reference_type', Refund::class)
            ->whereYear('stock_movements.created_at', now()->year)
            ->whereMonth('stock_movements.created_at', now()->month)
            ->groupBy('product_variants.product_id')
            ->select(['product_variants.product_id', DB::raw('SUM(stock_movements.quantity) as quantity_returned')])
            ->pluck('quantity_returned', 'product_id');

        return $sold
            ->map(fn ($row): array => [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'quantity_sold' => (int) $row->quantity_sold - (int) ($returned[$row->product_id] ?? 0),
            ])
            ->sortByDesc('quantity_sold')
            ->take($limit)
            ->values();
    }
}
