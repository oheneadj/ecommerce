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

    /**
     * Net revenue between two optional dates (inclusive) — feeds the
     * dashboard's "Today's Sales" stat when a date-range filter has been
     * applied via the header FilterAction. Either bound may be null.
     */
    public function revenueInRange(?string $start, ?string $end): int
    {
        return $this->netRevenue(function (Builder $query) use ($start, $end): Builder {
            if ($start) {
                $query->whereDate('created_at', '>=', $start);
            }

            if ($end) {
                $query->whereDate('created_at', '<=', $end);
            }

            return $query;
        });
    }

    /**
     * Orders placed between two optional dates (inclusive).
     */
    public function ordersCountInRange(?string $start, ?string $end): int
    {
        return $this->scopeToRange(Order::query(), $start, $end)->count();
    }

    /**
     * New customer signups between two optional dates (inclusive).
     */
    public function newCustomersCountInRange(?string $start, ?string $end): int
    {
        return $this->scopeToRange(User::query()->whereDoesntHave('roles'), $start, $end)->count();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopeToRange(Builder $query, ?string $start, ?string $end): Builder
    {
        if ($start) {
            $query->whereDate('created_at', '>=', $start);
        }

        if ($end) {
            $query->whereDate('created_at', '<=', $end);
        }

        return $query;
    }

    /**
     * Net revenue per day for the last $days days (oldest first) — feeds
     * the dashboard stat card's sparkline.
     *
     * @return array<int, float>
     */
    public function dailySalesTrend(int $days = 7): array
    {
        return collect(range($days - 1, 0))
            ->map(fn (int $offset): float => $this->netRevenue(
                fn (Builder $query): Builder => $query->whereDate('created_at', now()->subDays($offset)->toDateString()),
            ) / 100)
            ->all();
    }

    /**
     * Orders placed per day for the last $days days (oldest first).
     *
     * @return array<int, int>
     */
    public function dailyOrdersTrend(int $days = 7): array
    {
        return collect(range($days - 1, 0))
            ->map(fn (int $offset): int => Order::query()
                ->whereDate('created_at', now()->subDays($offset)->toDateString())
                ->count())
            ->all();
    }

    /**
     * New customer signups per day for the last $days days (oldest first).
     *
     * @return array<int, int>
     */
    public function dailyNewCustomersTrend(int $days = 7): array
    {
        return collect(range($days - 1, 0))
            ->map(fn (int $offset): int => User::query()
                ->whereDoesntHave('roles')
                ->whereDate('created_at', now()->subDays($offset)->toDateString())
                ->count())
            ->all();
    }

    /**
     * Orders placed per calendar month for the last 12 months (oldest
     * first) alongside the same 12 months one year earlier — feeds the
     * "Orders Year-over-Year" comparison chart.
     *
     * @return array{labels: array<int, string>, current: array<int, int>, prior: array<int, int>}
     */
    public function ordersYearOverYear(): array
    {
        $labels = [];
        $current = [];
        $prior = [];

        foreach (range(11, 0) as $offset) {
            $month = now()->subMonths($offset);
            $labels[] = $month->format('M Y');
            $current[] = Order::query()
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $priorMonth = $month->copy()->subYear();
            $prior[] = Order::query()
                ->whereYear('created_at', $priorMonth->year)
                ->whereMonth('created_at', $priorMonth->month)
                ->count();
        }

        return ['labels' => $labels, 'current' => $current, 'prior' => $prior];
    }

    /**
     * New customer signups per calendar month for the last 12 months
     * (oldest first) — feeds the "Customer Growth" chart.
     *
     * @return array{labels: array<int, string>, counts: array<int, int>}
     */
    public function customerGrowthTrend(): array
    {
        $labels = [];
        $counts = [];

        foreach (range(11, 0) as $offset) {
            $month = now()->subMonths($offset);
            $labels[] = $month->format('M Y');
            $counts[] = User::query()
                ->whereDoesntHave('roles')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * Orders sitting in Pending ("awaiting processing") or Processing
     * ("stuck in processing") for longer than $days — surfaces orders that
     * likely need manual intervention, ranked oldest first. "Stuck" is
     * always judged against now() regardless of $start/$end — an order
     * from a past filtered period is either still stuck today or it
     * isn't; $start/$end only scope *which orders* (by creation date) are
     * considered at all, when the dashboard's FilterAction has a range applied.
     *
     * @return Builder<Order>
     */
    public function flaggedOrdersQuery(?string $start = null, ?string $end = null, int $days = 3): Builder
    {
        $cutoff = now()->subDays($days);

        return $this->scopeToRange(
            Order::query()
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing])
                ->where('created_at', '<=', $cutoff),
            $start,
            $end,
        )->orderBy('created_at');
    }

    /**
     * Products ranked by net revenue (line-item price × quantity) from
     * verified orders this calendar month — feeds the "Top Products by
     * Revenue" chart.
     *
     * @return Collection<int, array{product_id: int, product_name: string, revenue: int}>
     */
    public function topProductsByRevenue(int $limit = 10): Collection
    {
        return $this->topProductsByRevenueInRange(null, null, $limit);
    }

    /**
     * Same ranking as topProductsByRevenue(), scoped to an optional date
     * range instead of the current calendar month — used when the
     * dashboard's FilterAction has a range applied. Uses the
     * permanently-snapshotted `unit_price` on each order item, never the
     * live product price.
     *
     * @return Collection<int, array{product_id: int, product_name: string, revenue: int}>
     */
    public function topProductsByRevenueInRange(?string $start, ?string $end, int $limit = 10): Collection
    {
        $statuses = array_map(fn (OrderStatus $status) => $status->value, self::VERIFIED_ORDER_STATUSES);

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('orders.status', $statuses);

        if ($start || $end) {
            if ($start) {
                $query->whereDate('orders.created_at', '>=', $start);
            }

            if ($end) {
                $query->whereDate('orders.created_at', '<=', $end);
            }
        } else {
            $query->whereYear('orders.created_at', now()->year)
                ->whereMonth('orders.created_at', now()->month);
        }

        return $query
            ->groupBy('products.id', 'products.name')
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('SUM(order_items.unit_price * order_items.quantity) as revenue'),
            ])
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'revenue' => (int) $row->revenue,
            ]);
    }

    /**
     * Customers bucketed by how many verified orders they've placed —
     * feeds the "Customer Segments" donut chart. Mirrors the "verified
     * purchase" definition used elsewhere on this dashboard; customers with
     * zero verified orders aren't a "segment" and are excluded.
     *
     * @return array{one_time: int, occasional: int, regular: int, vip: int}
     */
    public function customerSegments(): array
    {
        return $this->customerSegmentsInRange(null, null);
    }

    /**
     * Same segmentation as customerSegments(), but counting only orders
     * placed within an optional date range instead of all-time — used
     * when the dashboard's FilterAction has a range applied. A customer's
     * segment can therefore differ from their all-time segment when
     * scoped to a shorter period (e.g. a VIP all-time but only a one-time
     * buyer within the selected window).
     *
     * @return array{one_time: int, occasional: int, regular: int, vip: int}
     */
    public function customerSegmentsInRange(?string $start, ?string $end): array
    {
        $statuses = array_map(fn (OrderStatus $status) => $status->value, self::VERIFIED_ORDER_STATUSES);

        $orderCounts = User::query()
            ->whereDoesntHave('roles')
            ->withCount(['orders' => function (Builder $query) use ($statuses, $start, $end): Builder {
                $query->whereIn('status', $statuses);

                return $this->scopeToRange($query, $start, $end);
            }])
            ->get()
            ->pluck('orders_count')
            ->filter(fn (int $count) => $count > 0);

        return [
            'one_time' => $orderCounts->filter(fn (int $count) => $count === 1)->count(),
            'occasional' => $orderCounts->filter(fn (int $count) => $count >= 2 && $count <= 3)->count(),
            'regular' => $orderCounts->filter(fn (int $count) => $count >= 4 && $count <= 9)->count(),
            'vip' => $orderCounts->filter(fn (int $count) => $count >= 10)->count(),
        ];
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
        return $this->topProductsInRange(null, null, $limit);
    }

    /**
     * Same ranking as topProducts(), scoped to an optional date range
     * instead of the current calendar month — used when the dashboard's
     * FilterAction has a range applied.
     *
     * @return Collection<int, array{product_id: int, product_name: string, quantity_sold: int}>
     */
    public function topProductsInRange(?string $start, ?string $end, int $limit = 5): Collection
    {
        $statuses = array_map(fn (OrderStatus $status) => $status->value, self::VERIFIED_ORDER_STATUSES);

        $soldQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('orders.status', $statuses);

        $returnedQuery = DB::table('stock_movements')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->where('stock_movements.type', StockMovementType::Return->value)
            ->where('stock_movements.reference_type', Refund::class);

        if ($start || $end) {
            if ($start) {
                $soldQuery->whereDate('orders.created_at', '>=', $start);
                $returnedQuery->whereDate('stock_movements.created_at', '>=', $start);
            }

            if ($end) {
                $soldQuery->whereDate('orders.created_at', '<=', $end);
                $returnedQuery->whereDate('stock_movements.created_at', '<=', $end);
            }
        } else {
            $soldQuery->whereYear('orders.created_at', now()->year)
                ->whereMonth('orders.created_at', now()->month);
            $returnedQuery->whereYear('stock_movements.created_at', now()->year)
                ->whereMonth('stock_movements.created_at', now()->month);
        }

        $sold = $soldQuery
            ->groupBy('products.id', 'products.name')
            ->select(['products.id as product_id', 'products.name as product_name', DB::raw('SUM(order_items.quantity) as quantity_sold')])
            ->get();

        $returned = $returnedQuery
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
