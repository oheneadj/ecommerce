<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_an_orders_status_logs_a_history_entry(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $admin = User::factory()->create();

        UpdateOrderStatus::run($order, OrderStatus::Paid, $admin, 'Payment confirmed');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $history = $order->statusHistories()->latest('id')->first();
        $this->assertSame(OrderStatus::Paid, $history->status);
        $this->assertSame('Payment confirmed', $history->note);
        $this->assertSame($admin->id, $history->changed_by);
    }

    public function test_system_triggered_status_change_has_no_changed_by(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        UpdateOrderStatus::run($order, OrderStatus::Cancelled);

        $history = $order->statusHistories()->latest('id')->first();
        $this->assertNull($history->changed_by);
    }
}
