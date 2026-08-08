<?php

/**
 * Covers filtering dashboard widget data via the header FilterAction modal
 * (Filament's HasFiltersAction pattern) instead of an always-visible
 * filters form — applying a date range should update the stats/table
 * widgets, and leaving it empty should keep their default windows.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Widgets\CustomerGrowthWidget;
use App\Filament\Widgets\CustomerSegmentsWidget;
use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\FlaggedOrdersWidget;
use App\Filament\Widgets\OrdersOverviewWidget;
use App\Filament\Widgets\OrdersYearOverYearWidget;
use App\Filament\Widgets\ProductsOverviewWidget;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Queries\DashboardMetricsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardFilterActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_the_dashboard_page_exposes_a_filter_action_in_its_header(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Dashboard::class)
            ->assertActionExists('filter');
    }

    public function test_applying_the_filter_action_scopes_the_stats_widget_to_the_chosen_range(): void
    {
        $this->actingAs($this->admin());

        Payment::factory()->create(['status' => PaymentStatus::Success, 'amount' => 5000, 'created_at' => now()->subDays(20)]);
        Payment::factory()->create(['status' => PaymentStatus::Success, 'amount' => 1500, 'created_at' => now()]);

        $dashboard = Livewire::test(Dashboard::class)
            ->callAction('filter', data: [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        Livewire::test(DashboardStatsOverview::class, ['pageFilters' => $dashboard->get('filters')])
            ->assertSee('GH₵15.00');
    }

    public function test_applying_the_filter_action_scopes_recent_orders_to_the_chosen_range(): void
    {
        $this->actingAs($this->admin());

        $inRange = Order::factory()->create(['created_at' => now()]);
        $outOfRange = Order::factory()->create(['created_at' => now()->subDays(30)]);

        Livewire::test(RecentOrdersWidget::class, [
            'pageFilters' => [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ],
        ])
            ->assertSee($inRange->order_number)
            ->assertDontSee($outOfRange->order_number);
    }

    public function test_default_dashboard_load_has_no_filters_applied(): void
    {
        $this->actingAs($this->admin());

        $this->assertNull(Livewire::test(Dashboard::class)->get('filters'));
    }

    public function test_admin_sees_exactly_three_stats_for_a_uniform_grid(): void
    {
        $this->actingAs($this->admin());

        $widget = new DashboardStatsOverview;
        $stats = (new \ReflectionMethod($widget, 'getStats'))->invoke($widget);

        $this->assertCount(3, $stats);
    }

    public function test_the_dashboard_does_not_render_resource_scoped_widgets(): void
    {
        $this->actingAs($this->admin());

        $this->assertNotContains(ProductsOverviewWidget::class, (new Dashboard)->getWidgets());
        $this->assertNotContains(OrdersOverviewWidget::class, (new Dashboard)->getWidgets());
    }

    public function test_filtering_the_dashboard_does_not_affect_the_products_page_widget(): void
    {
        $this->actingAs($this->admin());
        Product::factory()->count(2)->create();

        Livewire::test(Dashboard::class)
            ->callAction('filter', data: [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        // ListProducts is an entirely separate Livewire component tree —
        // it never receives the dashboard's pageFilters, so it must show
        // the same thing regardless of what was just applied there.
        Livewire::test(ListProducts::class)->assertSuccessful();
        Livewire::test(ProductsOverviewWidget::class)->assertSee('Total Products')->assertSee('2');
    }

    public function test_filtering_the_dashboard_does_not_affect_the_orders_page_widget(): void
    {
        $this->actingAs($this->admin());
        Order::factory()->count(3)->create();

        Livewire::test(Dashboard::class)
            ->callAction('filter', data: [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        Livewire::test(ListOrders::class)->assertSuccessful();
        Livewire::test(OrdersOverviewWidget::class)->assertSee('Total Orders')->assertSee('3');
    }

    public function test_customer_growth_widget_scopes_to_the_chosen_range(): void
    {
        $this->actingAs($this->admin());

        User::factory()->create(['created_at' => now()->subDays(20)]);
        User::factory()->create(['created_at' => now()]);

        Livewire::test(Dashboard::class)
            ->callAction('filter', data: [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        Livewire::test(CustomerGrowthWidget::class, [
            'pageFilters' => [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ],
        ])->assertSee('Customer Growth (selected period)');
    }

    public function test_orders_year_over_year_widget_scopes_to_the_chosen_range(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(OrdersYearOverYearWidget::class, [
            'pageFilters' => [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ],
        ])
            ->assertSee('Orders (selected period vs. prior year)')
            ->assertSee('Selected Period')
            ->assertSee('Same Period, Prior Year');
    }

    public function test_customer_segments_widget_scopes_to_the_chosen_range(): void
    {
        $this->actingAs($this->admin());

        $customer = User::factory()->create();
        Order::factory()->create(['user_id' => $customer->id, 'status' => OrderStatus::Paid, 'created_at' => now()->subDays(20)]);
        Order::factory()->create(['user_id' => $customer->id, 'status' => OrderStatus::Paid, 'created_at' => now()]);

        // All-time this customer is a 2-order "occasional" buyer; within
        // the last 2 days they're only a 1-order "one-time" buyer.
        $unfiltered = app(DashboardMetricsQuery::class)->customerSegmentsInRange(null, null);
        $filtered = app(DashboardMetricsQuery::class)->customerSegmentsInRange(now()->subDays(2)->toDateString(), now()->toDateString());

        $this->assertSame(1, $unfiltered['occasional']);
        $this->assertSame(1, $filtered['one_time']);

        Livewire::test(CustomerSegmentsWidget::class, [
            'pageFilters' => [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ],
        ])->assertSee('Customer Segments (selected period)');
    }

    public function test_flagged_orders_widget_scopes_to_the_chosen_range(): void
    {
        $this->actingAs($this->admin());

        $inRange = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'created_at' => now()->subDays(5),
        ]);
        $outOfRange = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'created_at' => now()->subDays(30),
        ]);

        Livewire::test(FlaggedOrdersWidget::class, [
            'pageFilters' => [
                'startDate' => now()->subDays(10)->toDateString(),
                'endDate' => now()->toDateString(),
            ],
        ])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$outOfRange]);
    }
}
