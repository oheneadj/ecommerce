<?php

/**
 * Header stats for the Products list page.
 */

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Concerns\HasFormattedMoney;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Matches DashboardStatsOverview's card style (description + icon +
 * sparkline) — flat-line sparklines here, same reasoning as that widget's
 * "Low Stock Items" stat: these are live/point-in-time figures, not a
 * per-day series, so a flat line at the current value is honest rather
 * than fabricating a trend that doesn't exist.
 */
class ProductsOverviewWidget extends StatsOverviewWidget
{
    use HasFormattedMoney;

    /**
     * Forced to 3 for uniformity across every StatsOverviewWidget in the
     * admin panel, rather than relying on Filament's count-based default.
     */
    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $totalProducts = Product::query()->count();

        $activeVariants = ProductVariant::query()->where('status', VariantStatus::Active);
        $totalStockUnits = (int) $activeVariants->clone()->sum('stock');
        $totalInventoryValue = (int) $activeVariants->clone()->sum(DB::raw('stock * price'));

        return [
            Stat::make('Total Products', (string) $totalProducts)
                ->description('All products in the catalog')
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color('primary')
                ->chart(array_fill(0, 7, $totalProducts))
                ->chartColor('primary'),

            Stat::make('Stock On Hand', number_format($totalStockUnits).' units')
                ->description('Active variants only')
                ->descriptionIcon(Heroicon::OutlinedArchiveBox)
                ->color('info')
                ->chart(array_fill(0, 7, $totalStockUnits))
                ->chartColor('info'),

            Stat::make('Inventory Value', $this->formattedMoney($totalInventoryValue))
                ->description('Stock × price, active variants only')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->chart(array_fill(0, 7, $totalInventoryValue))
                ->chartColor('success'),
        ];
    }
}
