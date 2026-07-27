<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Queries\DashboardMetricsQuery;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * A plain widget (not a TableWidget) — DashboardMetricsQuery::topProducts()
 * runs a grouped aggregate join, not something a TableWidget's Eloquent
 * ->query() can express directly.
 */
class TopProductsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.top-products-widget';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $metrics = app(DashboardMetricsQuery::class);

        return [
            'products' => ($startDate || $endDate)
                ? $metrics->topProductsInRange($startDate, $endDate)
                : $metrics->topProducts(),
        ];
    }
}
