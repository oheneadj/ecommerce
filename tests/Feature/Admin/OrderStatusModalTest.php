<?php

/**
 * Covers updating an order's status via the table's "Update status" modal
 * action, rather than navigating to a full edit page.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderStatusModalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_update_status_action_updates_the_order_without_navigating_away(): void
    {
        $this->actingAs($this->admin());

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        Livewire::test(ListOrders::class)
            ->callTableAction('updateStatus', $order, data: [
                'status' => OrderStatus::Paid->value,
                'status_change_note' => 'Payment confirmed manually.',
            ])
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(
            'Payment confirmed manually.',
            $order->statusHistories()->latest()->first()?->note,
        );
    }

    public function test_the_update_status_action_is_not_a_url_based_navigation(): void
    {
        $this->actingAs($this->admin());

        $order = Order::factory()->create();

        $action = Livewire::test(ListOrders::class)
            ->instance()
            ->getTable()
            ->getAction('updateStatus');

        $this->assertNotNull($action);
        $this->assertNull($action->getUrl());
    }

    public function test_there_is_no_dedicated_order_edit_page_route(): void
    {
        $this->actingAs($this->admin());

        $order = Order::factory()->create();

        $this->get("/admin/orders/{$order->getKey()}/edit")->assertNotFound();
    }
}
