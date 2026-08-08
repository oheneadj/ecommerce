<?php

/**
 * Header stats for the Orders list page.
 */

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrdersOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Orders', (string) Order::query()->count())
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->color('primary'),

            Stat::make('Pending Orders', (string) Order::query()->where('status', OrderStatus::Pending)->count())
                ->description('Awaiting payment')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info'),
        ];
    }
}
