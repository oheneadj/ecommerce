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
 * Shows daily revenue across the dashboard's applied date-range filter when
 * one is set; otherwise falls back to the last 6 calendar months.
 */
class MonthlyRevenueChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Revenue (last 6 months)';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    public function getHeading(): ?string
    {
        return $this->hasRange() ? 'Revenue (selected period)' : 'Revenue (last 6 months)';
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
            // instead, the same granularity the no-range fallback uses.
            // Matters for wide ranges like the dashboard's "All time"
            // filter, which can span years.
            if ($days > 62) {
                $months = collect();

                for ($cursor = $start->startOfMonth(); $cursor->lte($end); $cursor = $cursor->addMonth()) {
                    $months->push($cursor);
                }

                $revenue = $months->map(fn (CarbonImmutable $month) => $metrics->revenueForMonth($month) / 100);

                return [
                    'datasets' => [
                        [
                            'label' => 'Revenue (GH₵)',
                            'data' => $revenue->values()->all(),
                        ],
                    ],
                    'labels' => $months->map(fn (CarbonImmutable $month) => $month->format('M Y'))->all(),
                ];
            }

            $dates = collect(range(0, (int) max(0, $days - 1)))->map(fn (int|float $offset) => $start->addDays((int) $offset));
            // One pair of queries for the whole range instead of a pair per day.
            $revenueByDay = $metrics->revenueByDay($start->toDateString(), $end->toDateString());
            $revenue = $dates->map(fn (CarbonImmutable $date) => (int) ($revenueByDay[$date->toDateString()] ?? 0) / 100);

            return [
                'datasets' => [
                    [
                        'label' => 'Revenue (GH₵)',
                        'data' => $revenue->values()->all(),
                    ],
                ],
                'labels' => $dates->map(fn (CarbonImmutable $date) => $date->format('d M'))->all(),
            ];
        }

        $storeNow = $metrics->storeNow();
        $months = collect(range(5, 0))->map(fn (int $offset) => $storeNow->copy()->subMonths($offset));

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

    private function hasRange(): bool
    {
        return ! empty($this->filters['startDate'] ?? null) || ! empty($this->filters['endDate'] ?? null);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
