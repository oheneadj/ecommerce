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
 * Shows daily new-customer counts across the dashboard's applied
 * date-range filter when one is set; otherwise falls back to the last
 * 12 calendar months.
 */
class CustomerGrowthWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    public function getHeading(): ?string
    {
        return $this->hasRange() ? 'Customer Growth (selected period)' : 'Customer Growth (last 12 months)';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $metrics = app(DashboardMetricsQuery::class);

        if ($this->hasRange()) {
            $storeNow = $metrics->storeNow();
            $start = CarbonImmutable::parse($this->filters['startDate'] ?? $storeNow->copy()->subDays(6)->toDateString());
            $end = CarbonImmutable::parse($this->filters['endDate'] ?? $storeNow->toDateString());
            $days = $start->diffInDays($end) + 1;

            // Beyond ~2 months, a day-by-day breakdown means a query per
            // day (and an unreadable chart) — switch to monthly points
            // instead. Matters for wide ranges like the dashboard's
            // "All time" filter, which can span years.
            if ($days > 62) {
                $months = collect();

                for ($cursor = $start->startOfMonth(); $cursor->lte($end); $cursor = $cursor->addMonth()) {
                    $months->push($cursor);
                }

                $counts = $months->map(fn (CarbonImmutable $month) => $metrics->newCustomersCountInRange(
                    $month->startOfMonth()->toDateString(),
                    $month->endOfMonth()->toDateString(),
                ));

                return [
                    'datasets' => [
                        [
                            'label' => 'New Customers',
                            'data' => $counts->values()->all(),
                            'fill' => true,
                            'borderColor' => '#22c55e',
                            'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                        ],
                    ],
                    'labels' => $months->map(fn (CarbonImmutable $month) => $month->format('M Y'))->all(),
                ];
            }

            $dates = collect(range(0, (int) max(0, $days - 1)))->map(fn (int|float $offset) => $start->addDays((int) $offset));
            // One query for the whole range instead of one per day.
            $countsByDay = $metrics->newCustomersCountByDay($start->toDateString(), $end->toDateString());
            $counts = $dates->map(fn (CarbonImmutable $date) => (int) ($countsByDay[$date->toDateString()] ?? 0));

            return [
                'datasets' => [
                    [
                        'label' => 'New Customers',
                        'data' => $counts->values()->all(),
                        'fill' => true,
                        'borderColor' => '#22c55e',
                        'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    ],
                ],
                'labels' => $dates->map(fn (CarbonImmutable $date) => $date->format('d M'))->all(),
            ];
        }

        $trend = $metrics->customerGrowthTrend();

        return [
            'datasets' => [
                [
                    'label' => 'New Customers',
                    'data' => $trend['counts'],
                    'fill' => true,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
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
