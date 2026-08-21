<?php

/**
 * Covers the per-day chart branch shared by CustomerGrowthWidget,
 * MonthlyRevenueChart, and OrdersYearOverYearWidget — previously each
 * issued one (or two, for the year-over-year comparison) queries per day
 * in the selected range, a real query fan-out from a single ordinary
 * admin date-range filter action. Now grouped into one query per series,
 * independent of how many days are in the range. Data correctness of the
 * underlying day-bucketing is covered directly on DashboardMetricsQuery
 * (see DashboardMetricsQueryTest) — this only proves the query count.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Widgets\CustomerGrowthWidget;
use App\Filament\Widgets\MonthlyRevenueChart;
use App\Filament\Widgets\OrdersYearOverYearWidget;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardChartQueryCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Primed once here so its one-time row-creation cost (an INSERT
        // plus an activity-log entry, since store_settings doesn't exist
        // yet in a fresh RefreshDatabase test) never counts toward the
        // query-count assertions below — those exist to catch a
        // per-day/per-record fan-out, not to measure this unrelated
        // first-touch cost.
        StoreSetting::current();
    }

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $callback();

        return $count;
    }

    /**
     * A 60-day range — long enough that the old one-query-per-day
     * behavior would have produced ~60 (or ~120) queries. The exact
     * incidental query count around a Livewire component mount (auth
     * resolution, session, etc.) isn't the point here and shouldn't be
     * hardcoded; what matters is that the count stays flat and small
     * regardless of range length, proving the fan-out is gone.
     */
    private function rangeFilters(): array
    {
        return [
            'startDate' => now()->subDays(59)->toDateString(),
            'endDate' => now()->toDateString(),
        ];
    }

    public function test_customer_growth_widget_query_count_does_not_scale_with_range_length(): void
    {
        $this->actingAs($this->admin());

        $queries = $this->countQueries(function (): void {
            Livewire::test(CustomerGrowthWidget::class, ['pageFilters' => $this->rangeFilters()])
                ->call('updateChartData');
        });

        $this->assertLessThan(10, $queries);
    }

    public function test_monthly_revenue_chart_query_count_does_not_scale_with_range_length(): void
    {
        $this->actingAs($this->admin());

        $queries = $this->countQueries(function (): void {
            Livewire::test(MonthlyRevenueChart::class, ['pageFilters' => $this->rangeFilters()])
                ->call('updateChartData');
        });

        $this->assertLessThan(10, $queries);
    }

    public function test_orders_year_over_year_widget_query_count_does_not_scale_with_range_length(): void
    {
        $this->actingAs($this->admin());

        $queries = $this->countQueries(function (): void {
            Livewire::test(OrdersYearOverYearWidget::class, ['pageFilters' => $this->rangeFilters()])
                ->call('updateChartData');
        });

        $this->assertLessThan(10, $queries);
    }
}
