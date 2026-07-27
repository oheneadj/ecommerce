<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Actions\Order\AssignShipment;
use App\Actions\Order\ClaimGuestOrder;
use App\Actions\Order\GenerateOrderInvoice;
use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\GuestOrderClaimException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderShipped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_invoice_is_generated_and_path_stored_on_the_order(): void
    {
        Storage::fake('local');

        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->create(['order_number' => 'ORD-2026-000001']);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'item_snapshot' => ['product_name' => 'Test Product', 'sku' => 'SKU-1'],
        ]);

        $path = GenerateOrderInvoice::run($order);

        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $order->fresh()->invoice_path);
    }

    public function test_invoice_rendering_does_not_read_live_product_data(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'item_snapshot' => ['product_name' => 'Snapshotted Name', 'sku' => 'SNAP-SKU'],
            'unit_price' => 1234,
        ]);

        // The live variant/product is now gone entirely.
        $variant->product()->delete();
        $variant->delete();

        $order->refresh()->load('items');
        $html = view('pdf.order-invoice', ['order' => $order])->render();

        $this->assertStringContainsString('Snapshotted Name', $html);
        $this->assertStringContainsString('SNAP-SKU', $html);
    }

    public function test_guest_order_claim_requires_matching_authenticated_user_email(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'shopper@example.com']);
        $user = User::factory()->create(['email' => 'someone-else@example.com']);

        $this->expectException(GuestOrderClaimException::class);

        ClaimGuestOrder::run($order, $user);
    }

    public function test_guest_order_claim_succeeds_when_email_matches(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'shopper@example.com']);
        $user = User::factory()->create(['email' => 'shopper@example.com']);

        $result = ClaimGuestOrder::run($order, $user);

        $this->assertSame($user->id, $result->user_id);
    }

    public function test_claiming_an_already_attached_order_is_rejected(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'guest_email' => null]);
        $otherUser = User::factory()->create(['email' => 'someone@example.com']);

        $this->expectException(GuestOrderClaimException::class);

        ClaimGuestOrder::run($order, $otherUser);
    }

    public function test_assigning_a_shipment_creates_it_with_dispatched_status(): void
    {
        $order = Order::factory()->create();
        $method = ShippingMethod::factory()->create();

        $shipment = AssignShipment::run($order, $method, 'TRACK123');

        $this->assertSame(ShipmentStatus::Dispatched, $shipment->status);
        $this->assertSame('TRACK123', $shipment->tracking_number);
        $this->assertNotNull($shipment->dispatched_at);
    }

    public function test_reassigning_a_shipment_updates_the_existing_record_instead_of_duplicating(): void
    {
        $order = Order::factory()->create();
        $methodA = ShippingMethod::factory()->create();
        $methodB = ShippingMethod::factory()->create();

        AssignShipment::run($order, $methodA, 'FIRST');
        $second = AssignShipment::run($order, $methodB, 'SECOND');

        $this->assertSame(1, $order->fresh()->shipment()->count());
        $this->assertSame('SECOND', $second->tracking_number);
        $this->assertSame($methodB->id, $second->shipping_method_id);
    }

    public function test_reassigning_an_already_dispatched_shipment_does_not_reset_dispatched_at_or_resend_the_notification(): void
    {
        Notification::fake();

        $order = Order::factory()->create(['user_id' => User::factory()->create()->id]);
        $methodA = ShippingMethod::factory()->create();
        $methodB = ShippingMethod::factory()->create();

        $first = AssignShipment::run($order, $methodA, 'FIRST');
        $originalDispatchedAt = $first->dispatched_at;

        $second = AssignShipment::run($order, $methodB, 'SECOND');

        $this->assertSame($originalDispatchedAt->timestamp, $second->dispatched_at->timestamp);
        Notification::assertSentToTimes($order->user, OrderShipped::class, 1);
    }

    public function test_marking_an_order_delivered_also_marks_its_shipment_delivered(): void
    {
        $order = Order::factory()->create();
        $method = ShippingMethod::factory()->create();
        AssignShipment::run($order, $method);

        UpdateOrderStatus::run($order->fresh(), OrderStatus::Delivered);

        $shipment = $order->shipment()->sole();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
    }

    public function test_marking_an_order_delivered_with_no_shipment_does_not_error(): void
    {
        $order = Order::factory()->create();

        UpdateOrderStatus::run($order, OrderStatus::Delivered);

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }
}
