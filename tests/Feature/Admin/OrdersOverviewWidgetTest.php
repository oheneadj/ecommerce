<?php

/**
 * Covers the Orders list page's header stats: total order count and count
 * of orders awaiting payment (same definition as the sidebar nav badge
 * and DashboardMetricsQuery::pendingOrdersCount()).
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Widgets\OrdersOverviewWidget;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrdersOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_it_shows_total_pending_and_cancelled_order_counts(): void
    {
        $this->actingAs($this->admin());

        Order::factory()->count(2)->create(['status' => OrderStatus::Pending]);
        Order::factory()->create(['status' => OrderStatus::Paid]);
        Order::factory()->count(3)->create(['status' => OrderStatus::Cancelled]);

        Livewire::test(OrdersOverviewWidget::class)
            ->assertSee('Total Orders')
            ->assertSee('6')
            ->assertSee('Pending Orders')
            ->assertSee('2')
            ->assertSee('Cancelled Orders')
            ->assertSee('3');
    }

    public function test_it_has_exactly_three_stats_for_a_uniform_grid(): void
    {
        $this->actingAs($this->admin());

        $widget = new OrdersOverviewWidget;
        $stats = (new \ReflectionMethod($widget, 'getStats'))->invoke($widget);

        $this->assertCount(3, $stats);
    }

    public function test_the_orders_list_page_renders_with_the_overview_widget(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ListOrders::class)->assertSuccessful();
    }
}
