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
 * dashboard's own Pending Orders stat used to chart before that stat moved
 * here; Pending/Cancelled are live/point-in-time counts (not a per-day
 * series), so they get flat-line sparklines at the current value — same
 * reasoning as DashboardStatsOverview's "Low Stock Items" stat.
 *
 * Exactly 3 stats, matching every other StatsOverviewWidget in the admin
 * panel, for a uniform 3-per-row grid everywhere.
 */
class OrdersOverviewWidget extends StatsOverviewWidget
{
    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsQuery::class);
        $pendingCount = Order::query()->where('status', OrderStatus::Pending)->count();
        $cancelledCount = Order::query()->where('status', OrderStatus::Cancelled)->count();

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

            Stat::make('Cancelled Orders', (string) $cancelledCount)
                ->description('Never fulfilled')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->chart(array_fill(0, 7, $cancelledCount))
                ->chartColor('danger'),
        ];
    }
}
