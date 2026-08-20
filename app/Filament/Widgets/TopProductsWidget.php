<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard bar chart of top products by quantity sold, defaulting to
 * this month or the dashboard's date-range filter when one is applied.
 */
class TopProductsWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 9;

    /**
     * Visible to Admins/Super Admins only.
     */
    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    /**
     * Heading reflects whether a date range is currently applied.
     */
    public function getHeading(): string
    {
        return $this->hasRange() ? 'Top Products (selected period)' : 'Top Products (this month)';
    }

    /**
     * Renders as a horizontal bar chart.
     */
    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Chart.js options: horizontal bars, no legend.
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }

    /**
     * Top products by quantity sold, for the chart dataset.
     */
    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $metrics = app(DashboardMetricsQuery::class);

        $products = $this->hasRange()
            ? $metrics->topProductsInRange($startDate, $endDate)
            : $metrics->topProducts();

        return [
            'datasets' => [
                [
                    'label' => 'Quantity sold',
                    'data' => $products->pluck('quantity_sold')->all(),
                    'backgroundColor' => '#22c55e',
                ],
            ],
            'labels' => $products->pluck('product_name')->all(),
        ];
    }

    /**
     * Whether the dashboard's date-range filter is currently applied.
     */
    private function hasRange(): bool
    {
        return ! empty($this->filters['startDate'] ?? null) || ! empty($this->filters['endDate'] ?? null);
    }
}
