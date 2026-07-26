<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
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
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset));

        $revenue = $months->map(function ($month) {
            return (int) Payment::query()
                ->where('status', PaymentStatus::Success)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('amount') / 100;
        });

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
