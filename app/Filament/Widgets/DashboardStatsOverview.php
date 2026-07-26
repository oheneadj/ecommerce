<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Store Keeper only ever sees the Low Stock Items stat — their role never
 * extends to orders/payments/customers (BRD role table, enforced
 * identically to OrderPolicy/PaymentPolicy elsewhere in this system).
 */
class DashboardStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsQuery::class);
        $isStoreKeeperOnly = ! Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);

        $lowStock = Stat::make('Low Stock Items', (string) $metrics->lowStockCount())
            ->description('At or below their threshold')
            ->color('warning');

        if ($isStoreKeeperOnly) {
            return [$lowStock];
        }

        return [
            Stat::make("Today's Sales", $this->formatMoney($metrics->todaysSales()))
                ->color('success'),
            Stat::make('Pending Orders', (string) $metrics->pendingOrdersCount())
                ->color('info'),
            $lowStock,
            Stat::make('New Customers', (string) $metrics->newCustomersCount())
                ->description('This month'),
        ];
    }

    private function formatMoney(int $minorUnits): string
    {
        return 'GH₵'.number_format($minorUnits / 100, 2);
    }
}
