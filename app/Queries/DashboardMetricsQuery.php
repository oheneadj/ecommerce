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
use App\Models\StoreSetting;
use App\Models\User;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

    private ?StoreSetting $store = null;

    /**
     * Memoized per instance (a fresh instance is resolved per request/
     * widget render, so this can never leak stale data across requests)
     * — several methods here call this multiple times (or in a 12-month
     * trend loop), and `StoreSetting::current()` itself always hits the
     * database with no caching of its own.
     */
    private function store(): StoreSetting
    {
        return $this->store ??= StoreSetting::current();
    }

    /**
     * "Now", in the store's configured display timezone — every "today"/
     * "this month"/"N days ago" calculation below is anchored to this,
     * not the server's UTC clock, so an admin outside UTC sees data
     * bucketed by their own calendar day rather than the server's.
     */
    private function storeNow(): Carbon
    {
        return Carbon::now($this->store()->timezone);
    }

    public function todaysSales(): int
    {
        $today = $this->storeNow()->toDateString();

        return $this->revenueInRange($today, $today);
    }

    public function monthlyRevenue(): int
    {
        return $this->revenueForMonth($this->storeNow());
    }

    /**
     * Net revenue (successful payments minus successful refunds) for an
     * arbitrary calendar month — used by monthlyRevenue() for the current
     * month and by MonthlyRevenueChart for each of the last 6. `$month`
     * is interpreted as a calendar month in the store's timezone (via
     * revenueInRange()'s UTC-boundary conversion), not a UTC one.
     */
    public function revenueForMonth(DateTimeInterface $month): int
    {
        $month = Carbon::parse($month)->setTimezone($this->store()->timezone);

        return $this->revenueInRange($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString());
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
        return $this->netRevenue(fn (Builder $query): Builder => $this->scopeToRange($query, $start, $end));
    }

    /**
     * Orders placed between two optional dates (inclusive).
     */
    public function ordersCountInRange(?string $start, ?string $end): int
    {
        return $this->scopeToRange(Order::query(), $start, $end)->count();
    }

    /**
     * Same as ordersCountInRange(), but every day in the range in one
     * query instead of one query per day — used by dashboard charts
     * plotting a daily series, which previously called
     * ordersCountInRange() once per day in the range (e.g. 60+ queries
     * for a two-month chart). Missing days (no orders at all) simply
     * aren't in the result; callers should default to 0.
     *
     * @return Collection<string, int> keyed by 'Y-m-d'
     */
    public function ordersCountByDay(string $start, string $end): Collection
    {
        $store = $this->store();

        // The overall range boundary is timezone-correct (below), but the
        // per-row `DATE(created_at)` grouping is still a raw UTC calendar
        // day — a record near midnight in a non-UTC store's timezone can
        // still land in the neighboring day's bucket. Doing this
        // correctly needs a timezone-aware SQL date truncation, which
        // isn't portable across this app's MySQL (production) and SQLite
        // (test) drivers. Accepted as a smaller, separate limitation from
        // the range-boundary bug this fixes.
        return Order::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>=', $store->startOfDayUtc($start))
            ->where('created_at', '<=', $store->endOfDayUtc($end))
            ->groupBy('day')
            ->pluck('count', 'day');
    }

    /**
     * New customer signups between two optional dates (inclusive).
     */
    public function newCustomersCountInRange(?string $start, ?string $end): int
    {
        return $this->scopeToRange(User::query()->whereDoesntHave('roles'), $start, $end)->count();
    }

    /**
     * Same as newCustomersCountInRange(), but every day in one query —
     * see ordersCountByDay()'s docblock for why.
     *
     * @return Collection<string, int> keyed by 'Y-m-d'
     */
    public function newCustomersCountByDay(string $start, string $end): Collection
    {
        $store = $this->store();

        return User::query()
            ->whereDoesntHave('roles')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>=', $store->startOfDayUtc($start))
            ->where('created_at', '<=', $store->endOfDayUtc($end))
            ->groupBy('day')
            ->pluck('count', 'day');
    }

    /**
     * Same as revenueInRange(), but every day in the range in two queries
     * (one for payments, one for refunds) instead of two queries per day
     * — see ordersCountByDay()'s docblock for why.
     *
     * @return Collection<string, int> net revenue (minor units) keyed by 'Y-m-d'
     */
    public function revenueByDay(string $start, string $end): Collection
    {
        $store = $this->store();

        $paymentsByDay = Payment::query()
            ->where('status', PaymentStatus::Success)
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->where('created_at', '>=', $store->startOfDayUtc($start))
            ->where('created_at', '<=', $store->endOfDayUtc($end))
            ->groupBy('day')
            ->pluck('total', 'day');

        $refundsByDay = Refund::query()
            ->where('status', RefundStatus::Success)
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->where('created_at', '>=', $store->startOfDayUtc($start))
            ->where('created_at', '<=', $store->endOfDayUtc($end))
            ->groupBy('day')
            ->pluck('total', 'day');

        return $paymentsByDay->keys()
            ->merge($refundsByDay->keys())
            ->unique()
            ->mapWithKeys(fn (string $day): array => [
                $day => (int) ($paymentsByDay[$day] ?? 0) - (int) ($refundsByDay[$day] ?? 0),
            ]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopeToRange(Builder $query, ?string $start, ?string $end, string $column = 'created_at'): Builder
    {
        $store = $this->store();

        if ($start) {
            $query->where($column, '>=', $store->startOfDayUtc($start));
        }

        if ($end) {
            $query->where($column, '<=', $store->endOfDayUtc($end));
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
        $today = $this->storeNow();

        return collect(range($days - 1, 0))
            ->map(fn (int $offset): float => $this->revenueInRange(
                $today->copy()->subDays($offset)->toDateString(),
                $today->copy()->subDays($offset)->toDateString(),
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
        $today = $this->storeNow();

        return collect(range($days - 1, 0))
            ->map(fn (int $offset): int => $this->ordersCountInRange(
                $today->copy()->subDays($offset)->toDateString(),
                $today->copy()->subDays($offset)->toDateString(),
            ))
            ->all();
    }

    /**
     * New customer signups per day for the last $days days (oldest first).
     *
     * @return array<int, int>
     */
    public function dailyNewCustomersTrend(int $days = 7): array
    {
        $today = $this->storeNow();

        return collect(range($days - 1, 0))
            ->map(fn (int $offset): int => $this->newCustomersCountInRange(
                $today->copy()->subDays($offset)->toDateString(),
                $today->copy()->subDays($offset)->toDateString(),
            ))
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
        $now = $this->storeNow();

        foreach (range(11, 0) as $offset) {
            $month = $now->copy()->subMonths($offset);
            $labels[] = $month->format('M Y');
            $current[] = $this->ordersCountInRange($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString());
            $priorMonth = $month->copy()->subYear();
            $prior[] = $this->ordersCountInRange($priorMonth->copy()->startOfMonth()->toDateString(), $priorMonth->copy()->endOfMonth()->toDateString());
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
        $now = $this->storeNow();

        foreach (range(11, 0) as $offset) {
            $month = $now->copy()->subMonths($offset);
            $labels[] = $month->format('M Y');
            $counts[] = $this->newCustomersCountInRange($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString());
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

        $store = $this->store();

        if ($start || $end) {
            if ($start) {
                $query->where('orders.created_at', '>=', $store->startOfDayUtc($start));
            }

            if ($end) {
                $query->where('orders.created_at', '<=', $store->endOfDayUtc($end));
            }
        } else {
            $now = $this->storeNow();
            $query->where('orders.created_at', '>=', $store->startOfDayUtc($now->copy()->startOfMonth()->toDateString()))
                ->where('orders.created_at', '<=', $store->endOfDayUtc($now->copy()->endOfMonth()->toDateString()));
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

        // Bucketed entirely in SQL — this previously pulled every customer
        // row into PHP (via ->get()) just to bucket order counts with
        // ->filter(), a full-table load on every dashboard render that
        // gets worse as the customer base grows. A customer with zero
        // qualifying orders in the range never appears in this GROUP BY at
        // all, matching the original code's own `> 0` filter (they never
        // counted toward any segment either way).
        $perCustomerOrderCounts = Order::query()
            ->select('user_id')
            ->selectRaw('COUNT(*) as order_count')
            ->whereIn('status', $statuses)
            ->whereNotNull('user_id')
            ->whereHas('user', fn (Builder $query): Builder => $query->whereDoesntHave('roles'))
            ->tap(fn (Builder $query) => $this->scopeToRange($query, $start, $end))
            ->groupBy('user_id');

        $buckets = DB::query()
            ->fromSub($perCustomerOrderCounts, 'per_customer_order_counts')
            ->selectRaw('
                SUM(CASE WHEN order_count = 1 THEN 1 ELSE 0 END) as one_time,
                SUM(CASE WHEN order_count BETWEEN 2 AND 3 THEN 1 ELSE 0 END) as occasional,
                SUM(CASE WHEN order_count BETWEEN 4 AND 9 THEN 1 ELSE 0 END) as regular,
                SUM(CASE WHEN order_count >= 10 THEN 1 ELSE 0 END) as vip
            ')
            ->first();

        return [
            'one_time' => (int) ($buckets->one_time ?? 0),
            'occasional' => (int) ($buckets->occasional ?? 0),
            'regular' => (int) ($buckets->regular ?? 0),
            'vip' => (int) ($buckets->vip ?? 0),
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
        $now = $this->storeNow();

        return $this->newCustomersCountInRange($now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString());
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

        $store = $this->store();

        if ($start || $end) {
            if ($start) {
                $soldQuery->where('orders.created_at', '>=', $store->startOfDayUtc($start));
                $returnedQuery->where('stock_movements.created_at', '>=', $store->startOfDayUtc($start));
            }

            if ($end) {
                $soldQuery->where('orders.created_at', '<=', $store->endOfDayUtc($end));
                $returnedQuery->where('stock_movements.created_at', '<=', $store->endOfDayUtc($end));
            }
        } else {
            $now = $this->storeNow();
            $monthStart = $store->startOfDayUtc($now->copy()->startOfMonth()->toDateString());
            $monthEnd = $store->endOfDayUtc($now->copy()->endOfMonth()->toDateString());
            $soldQuery->where('orders.created_at', '>=', $monthStart)->where('orders.created_at', '<=', $monthEnd);
            $returnedQuery->where('stock_movements.created_at', '>=', $monthStart)->where('stock_movements.created_at', '<=', $monthEnd);
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
