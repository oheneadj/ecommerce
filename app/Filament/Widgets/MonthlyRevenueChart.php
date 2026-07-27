<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlyRevenueChart extends ChartWidget
{
    protected ?string $heading = 'Revenue (last 6 months)';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    protected function getData(): array
    {
        $metrics = app(DashboardMetricsQuery::class);
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset));

        $revenue = $months->map(fn ($month) => $metrics->revenueForMonth($month) / 100);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (GH₵)',
                    'data' => $revenue->values()->all(),
                ],
            ],
            'labels' => $months->map(fn ($month) => $month->format('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
