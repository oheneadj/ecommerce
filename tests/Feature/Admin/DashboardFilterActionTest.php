<?php

/**
 * Covers filtering dashboard widget data via the header FilterAction modal
 * (Filament's HasFiltersAction pattern) instead of an always-visible
 * filters form — applying a date range should update the stats/table
 * widgets, and leaving it empty should keep their default windows.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
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
}
