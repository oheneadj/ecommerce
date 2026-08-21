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
use App\Models\Order;
use App\Queries\DashboardMetricsQuery;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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
     * Greets the logged-in staff member by name instead of the generic
     * "Dashboard" — falls back to that generic title for the rare case
     * a user has no name set (e.g. a phone-only account somehow reaching
     * the admin panel).
     */
    public function getTitle(): string
    {
        $name = Auth::user()?->name;

        return $name ? "Welcome, {$name}" : 'Dashboard';
    }

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

    /**
     * The date-range filter action modal plus its "all time" and "reset" shortcuts.
     */
    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema([
                    DatePicker::make('startDate')
                        ->label('From')
                        ->native(false)
                        ->placeholder('Today')
                        ->default($this->storeNow()->toDateString()),

                    DatePicker::make('endDate')
                        ->label('To')
                        ->native(false)
                        ->placeholder('Today')
                        ->default($this->storeNow()->toDateString())
                        ->afterOrEqual('startDate'),
                ]),

            // Bounded by the earliest order rather than a fixed far-past
            // sentinel — a sentinel like 2000-01-01 would still work
            // (every widget below floors to whatever data actually
            // exists), but pads the range-mode charts with years of empty
            // months before the store's first real order.
            Action::make('allTimeFilter')
                ->label('All time')
                ->color('gray')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->action(function (): void {
                    $this->filters = [
                        'startDate' => $this->earliestOrderDate(),
                        'endDate' => $this->storeNow()->toDateString(),
                    ];
                }),

            Action::make('resetFilter')
                ->label('Reset')
                ->color('gray')
                ->icon(Heroicon::OutlinedXMark)
                ->visible(fn (): bool => filled($this->filters))
                ->action(function (): void {
                    $this->filters = null;
                }),
        ];
    }

    /**
     * States, in plain words, exactly which period the widgets below are
     * showing — no filter falls back to "the current calendar month"
     * since that's what most widgets themselves default to (today's
     * sales/this month's top products/etc. all key off "now").
     */
    public function getSubheading(): ?string
    {
        $start = $this->filters['startDate'] ?? null;
        $end = $this->filters['endDate'] ?? null;

        if (! $start && ! $end) {
            return $this->storeNow()->format('F').' Overview';
        }

        if ($start === $this->earliestOrderDate() && $end === $this->storeNow()->toDateString()) {
            return 'All Time Overview';
        }

        $startLabel = $start ? Carbon::parse($start)->format('M j, Y') : 'the beginning';
        $endLabel = $end ? Carbon::parse($end)->format('M j, Y') : 'today';

        return "Overview from {$startLabel} to {$endLabel}";
    }

    /**
     * Creation date of the store's first order, used to floor the "all time" range.
     */
    private function earliestOrderDate(): string
    {
        return Order::query()->oldest()->value('created_at')?->toDateString() ?? $this->storeNow()->toDateString();
    }

    /**
     * The store's configured "now", not the server's — matches the
     * semantics every widget on this page already uses (via
     * DashboardMetricsQuery) so "today"/"this month" here means the same
     * calendar day the widgets themselves are keyed off, not whatever the
     * server's raw timezone happens to be.
     */
    private function storeNow(): Carbon
    {
        return app(DashboardMetricsQuery::class)->storeNow();
    }
}
