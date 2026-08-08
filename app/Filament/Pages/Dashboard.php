<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\CustomerGrowthWidget;
use App\Filament\Widgets\CustomerSegmentsWidget;
use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\FlaggedOrdersWidget;
use App\Filament\Widgets\LowStockVariantsWidget;
use App\Filament\Widgets\MonthlyRevenueChart;
use App\Filament\Widgets\OrdersYearOverYearWidget;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Filament\Widgets\TopProductsByRevenueWidget;
use App\Filament\Widgets\TopProductsWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Widgets\Widget;

/**
 * Filters dashboard widget data via an action modal (rather than an
 * always-visible filters form) — widgets read the chosen date range through
 * InteractsWithPageFilters and fall back to their own default window
 * (today/this month/last 6 months) when no range has been applied.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    /**
     * Explicit allowlist rather than the panel's auto-discovered widget
     * set (`Filament::getWidgets()`, the base class's default) — every
     * widget class under app/Filament/Widgets/ is auto-registered
     * panel-wide by discoverWidgets(), which was silently rendering
     * resource-scoped widgets like ProductsOverviewWidget/
     * OrdersOverviewWidget on this page too. Those don't implement
     * InteractsWithPageFilters, so they never responded to this page's
     * date-range filter — looking like "the dashboard doesn't respond to
     * filtering" even though the widgets that actually belong here do.
     * They stay resource-page-only via ListProducts/ListOrders's own
     * getHeaderWidgets(), which is a separate mechanism this list has no
     * effect on either way.
     *
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            DashboardStatsOverview::class,
            RecentOrdersWidget::class,
            OrdersYearOverYearWidget::class,
            MonthlyRevenueChart::class,
            CustomerGrowthWidget::class,
            CustomerSegmentsWidget::class,
            FlaggedOrdersWidget::class,
            TopProductsByRevenueWidget::class,
            TopProductsWidget::class,
            LowStockVariantsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema([
                    DatePicker::make('startDate')
                        ->label('From')
                        ->native(false)
                        ->placeholder('Today')
                        ->default(now()->toDateString()),

                    DatePicker::make('endDate')
                        ->label('To')
                        ->native(false)
                        ->placeholder('Today')
                        ->default(now()->toDateString())
                        ->afterOrEqual('startDate'),
                ]),
        ];
    }
}
