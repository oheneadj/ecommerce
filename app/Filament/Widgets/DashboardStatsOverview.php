<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Support\Icons\Heroicon;
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
 */
class DashboardStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsQuery::class);
        $isStoreKeeperOnly = ! Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);

        $lowStockCount = $metrics->lowStockCount();

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

        return [
            Stat::make("Today's Sales", $this->formatMoney($metrics->todaysSales()))
                ->description('Net of refunds')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color('success')
                ->chart($metrics->dailySalesTrend())
                ->chartColor('success'),
            Stat::make('Pending Orders', (string) $metrics->pendingOrdersCount())
                ->description('Awaiting payment')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info')
                ->chart($metrics->dailyOrdersTrend())
                ->chartColor('info'),
            $lowStock,
            Stat::make('New Customers', (string) $metrics->newCustomersCount())
                ->description('This month')
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
