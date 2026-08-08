<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Store Keeper only ever sees the Low Stock Items stat — their role never
 * extends to orders/payments/customers (BRD role table, enforced
 * identically to OrderPolicy/PaymentPolicy elsewhere in this system).
 *
 * Each card carries a colored description with an icon and a 7-day
 * sparkline, matching the stat-card style requested for the dashboard.
 *
 * "Today's Sales" and "New Customers" respect the dashboard's date-range
 * FilterAction when one has been applied, falling back to their normal
 * today/this-month windows otherwise.
 *
 * Deliberately 3 cross-cutting business KPIs, not order-specific ones —
 * order-status breakdown (pending/cancelled) lives on the Orders list
 * page's own OrdersOverviewWidget instead, where it's actionable in
 * context. Keeps every StatsOverviewWidget in the admin panel at exactly
 * 3 stats for a uniform 3-per-row grid everywhere.
 */
class DashboardStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsQuery::class);
        $isStoreKeeperOnly = ! Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);

        $lowStockCount = $metrics->lowStockCount();
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $hasRange = $startDate || $endDate;

        // No historical snapshot of this count exists — it's a live figure,
        // not a per-day series — so the sparkline is a flat line at the
        // current value rather than a fabricated trend.
        $lowStock = Stat::make('Low Stock Items', (string) $lowStockCount)
            ->description('At or below their threshold')
            ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
            ->color('warning')
            ->chart(array_fill(0, 7, $lowStockCount))
            ->chartColor('warning');

        if ($isStoreKeeperOnly) {
            return [$lowStock];
        }

        $sales = $hasRange ? $metrics->revenueInRange($startDate, $endDate) : $metrics->todaysSales();
        $newCustomers = $hasRange ? $metrics->newCustomersCountInRange($startDate, $endDate) : $metrics->newCustomersCount();

        return [
            Stat::make('Sales', $this->formatMoney($sales))
                ->description($hasRange ? 'Selected period, net of refunds' : "Today's sales, net of refunds")
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color('success')
                ->chart($metrics->dailySalesTrend())
                ->chartColor('success'),
            $lowStock,
            Stat::make('New Customers', (string) $newCustomers)
                ->description($hasRange ? 'Selected period' : 'This month')
                ->descriptionIcon(Heroicon::OutlinedUserPlus)
                ->color('primary')
                ->chart($metrics->dailyNewCustomersTrend())
                ->chartColor('primary'),
        ];
    }

    private function formatMoney(int $minorUnits): string
    {
        return 'GH₵'.number_format($minorUnits / 100, 2);
    }
}
