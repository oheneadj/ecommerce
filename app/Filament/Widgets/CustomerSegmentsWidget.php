<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

/**
 * Segments customers by verified-order count within the dashboard's
 * applied date-range filter when one is set; otherwise all-time. A
 * customer's segment can differ from their all-time segment when scoped
 * to a shorter period (e.g. all-time VIP but only a one-time buyer within
 * the selected window).
 */
class CustomerSegmentsWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 6;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    public function getHeading(): ?string
    {
        return $this->hasRange() ? 'Customer Segments (selected period)' : 'Customer Segments (all-time)';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $segments = app(DashboardMetricsQuery::class)->customerSegmentsInRange($startDate, $endDate);

        return [
            'datasets' => [
                [
                    'data' => [
                        $segments['one_time'],
                        $segments['occasional'],
                        $segments['regular'],
                        $segments['vip'],
                    ],
                    'backgroundColor' => ['#9ca3af', '#3b82f6', '#22c55e', '#f59e0b'],
                ],
            ],
            'labels' => ['One-time (1)', 'Occasional (2-3)', 'Regular (4-9)', 'VIP (10+)'],
        ];
    }

    private function hasRange(): bool
    {
        return ! empty($this->filters['startDate'] ?? null) || ! empty($this->filters['endDate'] ?? null);
    }
}
