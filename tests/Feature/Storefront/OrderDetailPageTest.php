<?php

/**
 * Covers the customer-facing order detail/tracking page
 * (/account/orders/{order}).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Storefront\OrderDetailPage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Payment\FakePaymentGateway;
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

    private function enableFakeProvider(): void
    {
        FakePaymentGateway::reset();
        $this->app->make(PaymentManager::class)->extend('moolre', fn () => new FakePaymentGateway);
        DB::table('payment_provider_settings')->insert([
            'provider' => 'moolre',
            'enabled' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_failed_payment_shows_a_retry_button(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")->assertSee('Retry payment');
    }

    public function test_a_successful_payment_shows_no_retry_button(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Success]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")->assertDontSee('Retry payment');
    }

    public function test_retrying_a_failed_payment_starts_a_new_attempt_on_the_same_order(): void
    {
        $this->enableFakeProvider();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->call('retryPayment')
            ->assertRedirect('https://fake-gateway.test/pay/fake-ref-1');

        $this->assertSame(2, $order->fresh()->payments()->count());
        $this->assertSame(1, Order::query()->count());
    }

    public function test_retrying_when_the_order_is_already_paid_does_nothing(): void
    {
        $this->enableFakeProvider();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->call('retryPayment')
            ->assertNoRedirect();

        $this->assertSame(1, $order->fresh()->payments()->count());
    }

    public function test_retrying_with_no_failed_payment_does_nothing(): void
    {
        $this->enableFakeProvider();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Pending]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->call('retryPayment')
            ->assertNoRedirect();

        $this->assertSame(1, $order->fresh()->payments()->count());
    }
}
