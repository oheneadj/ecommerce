<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockReservation;
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

    public function test_setting_the_same_status_again_is_a_no_op(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        UpdateOrderStatus::run($order, OrderStatus::Paid);

        $this->assertSame(0, $order->statusHistories()->count());
    }

    public function test_an_order_cannot_skip_from_pending_straight_to_delivered(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        UpdateOrderStatus::run($order, OrderStatus::Delivered);
    }

    public function test_an_order_cannot_move_backwards_out_of_a_later_status(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        UpdateOrderStatus::run($order, OrderStatus::Pending);
    }

    public function test_a_delivered_order_cannot_be_moved_to_any_other_status(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->expectException(InvalidOrderStatusTransitionException::class);

        UpdateOrderStatus::run($order, OrderStatus::Cancelled);
    }

    public function test_a_rejected_transition_never_writes_a_history_entry(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        try {
            UpdateOrderStatus::run($order, OrderStatus::Delivered);
        } catch (InvalidOrderStatusTransitionException) {
            // expected
        }

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public function test_cancelling_a_paid_order_returns_its_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'quantity' => 3]);

        UpdateOrderStatus::run($order, OrderStatus::Cancelled);

        $this->assertSame(8, $variant->fresh()->stock);
        $this->assertSame(1, StockMovement::query()->where('product_variant_id', $variant->id)->where('type', StockMovementType::Return)->count());
    }

    public function test_cancelling_a_shipped_order_returns_its_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $order = Order::factory()->create(['status' => OrderStatus::Shipped]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);

        UpdateOrderStatus::run($order, OrderStatus::Cancelled);

        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_cancelling_a_pending_order_that_never_had_stock_decremented_does_not_touch_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'quantity' => 2,
            'status' => StockReservationStatus::Active,
        ]);

        UpdateOrderStatus::run($order, OrderStatus::Cancelled);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame(0, StockMovement::query()->where('product_variant_id', $variant->id)->count());
    }
}
