<?php

/**
 * Covers the customer-facing order detail/tracking page
 * (/account/orders/{order}).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\OrderDetailPage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $order = Order::factory()->create();

        $this->get("/account/orders/{$order->ulid}")->assertRedirect('/login');
    }

    public function test_a_customer_can_view_their_own_order_with_items_status_and_payments(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_snapshot' => [
                'recipient_name' => 'Ama Boateng',
                'phone' => '0244000000',
                'line1' => '12 Ring Road',
                'city' => 'Accra',
                'region' => 'Greater Accra',
            ],
        ]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'item_snapshot' => ['product_name' => 'Blue Sneakers', 'sku' => 'SKU-1']]);
        OrderStatusHistory::factory()->create(['order_id' => $order->id, 'note' => 'Order received']);
        Payment::factory()->create(['order_id' => $order->id]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Blue Sneakers')
            ->assertSee('Ama Boateng')
            ->assertSee('Order received');
    }

    public function test_a_customer_cannot_view_another_customers_order(): void
    {
        $user = User::factory()->create();
        $otherOrder = Order::factory()->create();
        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $otherOrder->ulid]);
    }
}
