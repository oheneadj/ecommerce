<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

class TopProductsByRevenueWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Top Products by Revenue';

    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
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

        $products = ($startDate || $endDate)
            ? $metrics->topProductsByRevenueInRange($startDate, $endDate)
            : $metrics->topProductsByRevenue();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $products->map(fn (array $product): float => $product['revenue'] / 100)->all(),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $products->pluck('product_name')->all(),
        ];
    }
}
