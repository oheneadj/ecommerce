<?php

/**
 * Header stats for the Products list page.
 */

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ProductsOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalProducts = Product::query()->count();

        $activeVariants = ProductVariant::query()->where('status', VariantStatus::Active);
        $totalStockUnits = (int) $activeVariants->clone()->sum('stock');
        $totalInventoryValue = (int) $activeVariants->clone()->sum(DB::raw('stock * price'));

        return [
            Stat::make('Total Products', (string) $totalProducts)
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color('primary'),

            Stat::make('Stock On Hand', number_format($totalStockUnits).' units')
                ->descriptionIcon(Heroicon::OutlinedArchiveBox)
                ->color('info'),

            Stat::make('Inventory Value', 'GH₵'.number_format($totalInventoryValue / 100, 2))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
        ];
    }
}
