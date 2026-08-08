<?php

/**
 * Header stats for the Orders list page.
 */

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Queries\DashboardMetricsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Matches DashboardStatsOverview's card style (description + icon +
 * sparkline). Total Orders reuses the same daily-orders trend the
 * dashboard's own Pending Orders stat charts; Pending Orders itself is a
 * live/point-in-time count (not a per-day series), so it gets a flat-line
 * sparkline at the current value — same reasoning as that widget's "Low
 * Stock Items" stat.
 */
class OrdersOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsQuery::class);
        $pendingCount = Order::query()->where('status', OrderStatus::Pending)->count();

        return [
            Stat::make('Total Orders', (string) Order::query()->count())
                ->description('All orders placed')
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->color('primary')
                ->chart($metrics->dailyOrdersTrend())
                ->chartColor('primary'),

            Stat::make('Pending Orders', (string) $pendingCount)
                ->description('Awaiting payment')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info')
                ->chart(array_fill(0, 7, $pendingCount))
                ->chartColor('info'),
        ];
    }
}
