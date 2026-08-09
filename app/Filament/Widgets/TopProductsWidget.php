<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

class TopProductsWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 9;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    public function getHeading(): string
    {
        return $this->hasRange() ? 'Top Products (selected period)' : 'Top Products (this month)';
    }

    protected function getType(): string
    {
        return 'bar';
    }

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

    private function hasRange(): bool
    {
        return ! empty($this->filters['startDate'] ?? null) || ! empty($this->filters['endDate'] ?? null);
    }
}
