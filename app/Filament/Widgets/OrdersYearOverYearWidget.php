<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

/**
 * Shows daily order counts across the dashboard's applied date-range
 * filter (against the same range one year earlier) when a filter is set;
 * otherwise falls back to the last 12 calendar months vs. the prior 12.
 */
class OrdersYearOverYearWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    public function getHeading(): ?string
    {
        return $this->hasRange() ? 'Orders (selected period vs. prior year)' : 'Orders Year-over-Year';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $metrics = app(DashboardMetricsQuery::class);

        if ($this->hasRange()) {
            $start = CarbonImmutable::parse($this->filters['startDate'] ?? now()->subDays(6)->toDateString());
            $end = CarbonImmutable::parse($this->filters['endDate'] ?? now()->toDateString());
            $days = $start->diffInDays($end) + 1;

            // Beyond ~2 months, a day-by-day breakdown means two queries
            // per day (and an unreadable chart) — switch to monthly
            // points instead. Matters for wide ranges like the
            // dashboard's "All time" filter, which can span years.
            if ($days > 62) {
                $months = collect();

                for ($cursor = $start->startOfMonth(); $cursor->lte($end); $cursor = $cursor->addMonth()) {
                    $months->push($cursor);
                }

                $current = $months->map(fn (CarbonImmutable $month) => $metrics->ordersCountInRange(
                    $month->startOfMonth()->toDateString(),
                    $month->endOfMonth()->toDateString(),
                ));
                $prior = $months->map(fn (CarbonImmutable $month) => $metrics->ordersCountInRange(
                    $month->subYear()->startOfMonth()->toDateString(),
                    $month->subYear()->endOfMonth()->toDateString(),
                ));

                return [
                    'datasets' => [
                        [
                            'label' => 'Selected Period',
                            'data' => $current->values()->all(),
                            'fill' => true,
                            'borderColor' => '#3b82f6',
                            'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                        ],
                        [
                            'label' => 'Same Period, Prior Year',
                            'data' => $prior->values()->all(),
                            'fill' => false,
                            'borderColor' => '#9ca3af',
                            'borderDash' => [5, 5],
                        ],
                    ],
                    'labels' => $months->map(fn (CarbonImmutable $month) => $month->format('M Y'))->all(),
                ];
            }

            $dates = collect(range(0, (int) max(0, $days - 1)))->map(fn (int|float $offset) => $start->addDays((int) $offset));
            $current = $dates->map(fn (CarbonImmutable $date) => $metrics->ordersCountInRange($date->toDateString(), $date->toDateString()));
            $prior = $dates->map(fn (CarbonImmutable $date) => $metrics->ordersCountInRange(
                $date->subYear()->toDateString(),
                $date->subYear()->toDateString(),
            ));

            return [
                'datasets' => [
                    [
                        'label' => 'Selected Period',
                        'data' => $current->values()->all(),
                        'fill' => true,
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    ],
                    [
                        'label' => 'Same Period, Prior Year',
                        'data' => $prior->values()->all(),
                        'fill' => false,
                        'borderColor' => '#9ca3af',
                        'borderDash' => [5, 5],
                    ],
                ],
                'labels' => $dates->map(fn (CarbonImmutable $date) => $date->format('d M'))->all(),
            ];
        }

        $trend = $metrics->ordersYearOverYear();

        return [
            'datasets' => [
                [
                    'label' => 'Last 12 Months',
                    'data' => $trend['current'],
                    'fill' => true,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                ],
                [
                    'label' => 'Prior 12 Months',
                    'data' => $trend['prior'],
                    'fill' => false,
                    'borderColor' => '#9ca3af',
                    'borderDash' => [5, 5],
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    private function hasRange(): bool
    {
        return ! empty($this->filters['startDate'] ?? null) || ! empty($this->filters['endDate'] ?? null);
    }
}
