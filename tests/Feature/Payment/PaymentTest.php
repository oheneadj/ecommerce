<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Actions\Payment\HandleLatePaymentConfirmation;
use App\Actions\Payment\HandlePaymentWebhook;
use App\Actions\Payment\InitiatePayment;
use App\Actions\Payment\ProcessRefund;
use App\Actions\Payment\VerifyPendingPayments;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Exceptions\InvalidRefundAmountException;
use App\Exceptions\RefundExceedsPaymentException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\WebhookEvent;
use App\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentGateway::reset();
        $this->app->make(PaymentManager::class)->extend('fake', fn () => new FakePaymentGateway);
        config([
            'payments.channels.mobile_money' => 'fake',
            'payments.channels.card' => 'fake',
        ]);
    }

    private function orderWithReservedItem(int $stock = 10, int $quantity = 2): Order
    {
        $variant = ProductVariant::factory()->create(['stock' => $stock]);
        $order = Order::factory()->create(['grand_total' => 2000]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);

        StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'quantity' => $quantity,
            'status' => StockReservationStatus::Active,
        ]);

        return $order->fresh();
    }

    public function test_initiating_a_payment_is_idempotent_per_order(): void
    {
        $order = Order::factory()->create();

        $first = InitiatePayment::run($order, 'mobile_money');
        $second = InitiatePayment::run($order, 'mobile_money');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_outbound_payment_api_calls_are_logged_to_order(): void
    {
        $order = Order::factory()->create();

        InitiatePayment::run($order, 'mobile_money');

        $this->assertSame(1, $order->fresh()->payments()->count());
        $this->assertDatabaseHas('payment_api_logs', [
            'order_id' => $order->id,
            'action' => 'initiate',
        ]);
    }

    public function test_failed_initiation_still_creates_a_failed_payment_and_log(): void
    {
        FakePaymentGateway::$initiateSucceeds = false;
        $order = Order::factory()->create();

        $payment = InitiatePayment::run($order, 'mobile_money');

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertDatabaseHas('payment_api_logs', ['order_id' => $order->id, 'status_code' => 422]);
    }

    public function test_unverified_webhook_signature_is_rejected(): void
    {
        FakePaymentGateway::$webhookSignatureValid = false;
        $order = $this->orderWithReservedItem();
        $payment = Payment::factory()->create(['order_id' => $order->id, 'provider' => 'fake', 'provider_reference' => 'fake-ref-1', 'status' => PaymentStatus::Pending]);

        $request = Request::create('/webhooks/payments/fake', 'POST', ['provider_reference' => 'fake-ref-1', 'event_id' => 'evt-1']);
        HandlePaymentWebhook::run($request, 'fake');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt-1', 'verified' => false]);
    }

    public function test_webhook_success_converts_reservation_to_stock_movement_and_marks_order_paid(): void
    {
        $order = $this->orderWithReservedItem(stock: 10, quantity: 3);
        $payment = Payment::factory()->create(['order_id' => $order->id, 'provider' => 'fake', 'provider_reference' => 'fake-ref-1', 'status' => PaymentStatus::Pending]);

        $request = Request::create('/webhooks/payments/fake', 'POST', ['provider_reference' => 'fake-ref-1', 'event_id' => 'evt-1']);
        HandlePaymentWebhook::run($request, 'fake');

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_reservation_converts_to_stock_movement_on_payment_success(): void
    {
        $order = $this->orderWithReservedItem(stock: 10, quantity: 3);
        $variant = $order->items()->first()->productVariant;
        $payment = Payment::factory()->create(['order_id' => $order->id, 'provider' => 'fake', 'provider_reference' => 'fake-ref-1', 'status' => PaymentStatus::Pending]);

        $request = Request::create('/webhooks/payments/fake', 'POST', ['provider_reference' => 'fake-ref-1', 'event_id' => 'evt-1']);
        HandlePaymentWebhook::run($request, 'fake');

        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'type' => StockMovementType::Sale->value,
            'quantity' => -3,
        ]);

        $reservation = StockReservation::query()->where('order_id', $order->id)->first();
        $this->assertSame(StockReservationStatus::Fulfilled, $reservation->status);
    }

    public function test_duplicate_webhook_event_does_not_double_process_payment(): void
    {
        $order = $this->orderWithReservedItem(stock: 10, quantity: 3);
        $variant = $order->items()->first()->productVariant;
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'fake', 'provider_reference' => 'fake-ref-1', 'status' => PaymentStatus::Pending]);

        $request = Request::create('/webhooks/payments/fake', 'POST', ['provider_reference' => 'fake-ref-1', 'event_id' => 'evt-1']);
        HandlePaymentWebhook::run($request, 'fake');
        HandlePaymentWebhook::run($request, 'fake');

        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame(1, WebhookEvent::query()->where('event_id', 'evt-1')->count());
    }

    public function test_polling_and_webhook_confirmation_are_mutually_idempotent(): void
    {
        $order = $this->orderWithReservedItem(stock: 10, quantity: 3);
        $variant = $order->items()->first()->productVariant;
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'fake',
            'provider_reference' => 'fake-ref-1',
            'status' => PaymentStatus::Pending,
            'created_at' => now()->subMinutes(5),
        ]);

        // Webhook settles it first.
        $request = Request::create('/webhooks/payments/fake', 'POST', ['provider_reference' => 'fake-ref-1', 'event_id' => 'evt-1']);
        HandlePaymentWebhook::run($request, 'fake');

        // Polling runs afterwards and must not re-process it.
        VerifyPendingPayments::run();

        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
    }

    public function test_late_payment_confirmation_refulfills_when_stock_available(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        // No active reservation — it already expired/released.
        $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Pending]);

        HandleLatePaymentConfirmation::run($payment);

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_late_payment_confirmation_auto_refunds_when_stock_unavailable(): void
    {
        FakePaymentGateway::$refundSucceeds = true;
        $variant = ProductVariant::factory()->create(['stock' => 1]);
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'quantity' => 5]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'fake',
            'provider_reference' => 'fake-ref-1',
            'amount' => 2000,
            'status' => PaymentStatus::Pending,
        ]);

        HandleLatePaymentConfirmation::run($payment);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 2000]);
    }

    public function test_partial_refund_cannot_exceed_original_payment_amount(): void
    {
        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);

        $this->expectException(RefundExceedsPaymentException::class);

        ProcessRefund::run($payment, 1500);
    }

    public function test_a_zero_refund_amount_is_rejected(): void
    {
        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);

        $this->expectException(InvalidRefundAmountException::class);

        ProcessRefund::run($payment, 0);
    }

    public function test_a_negative_refund_amount_is_rejected(): void
    {
        // Without this guard, a negative amount would pass the "does this
        // exceed the payment" cap check trivially, get queued to the real
        // payment gateway, and drive the proportional-restock math negative.
        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);

        $this->expectException(InvalidRefundAmountException::class);

        ProcessRefund::run($payment, -500);
    }

    public function test_an_invalid_refund_amount_never_creates_a_refund_row(): void
    {
        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);

        try {
            ProcessRefund::run($payment, -500);
        } catch (InvalidRefundAmountException) {
            // expected
        }

        $this->assertSame(0, $payment->refunds()->count());
    }

    public function test_refund_cannot_exceed_remaining_amount_after_a_prior_partial_refund(): void
    {
        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);
        ProcessRefund::run($payment, 600);

        $this->expectException(RefundExceedsPaymentException::class);

        ProcessRefund::run($payment, 500);
    }

    public function test_repeated_refunds_cannot_cumulatively_exceed_the_payment_amount(): void
    {
        // Same shape as the stock/coupon concurrency tests elsewhere in this
        // suite — proves the cap is enforced against the running total,
        // including refunds already claimed by earlier calls, not just the
        // original request in isolation.
        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);

        $succeeded = 0;
        $rejected = 0;

        for ($i = 0; $i < 5; $i++) {
            try {
                ProcessRefund::run($payment, 250);
                $succeeded++;
            } catch (RefundExceedsPaymentException) {
                $rejected++;
            }
        }

        $this->assertSame(4, $succeeded);
        $this->assertSame(1, $rejected);
        $this->assertSame(1000, $payment->refunds()->where('status', RefundStatus::Success)->sum('amount'));
    }

    public function test_a_pending_refund_counts_against_the_cap_before_the_gateway_confirms(): void
    {
        // The reservation (Pending Refund row) is what closes the race, not
        // the eventual Success/Failed status — a refund that's merely
        // in-flight must already block a second refund from over-claiming
        // the same balance.
        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);
        $payment->refunds()->create(['amount' => 700, 'status' => RefundStatus::Pending]);

        $this->expectException(RefundExceedsPaymentException::class);

        ProcessRefund::run($payment, 400);
    }

    public function test_refund_restores_stock_via_movement(): void
    {
        $order = $this->orderWithReservedItem(stock: 10, quantity: 4);
        $variant = $order->items()->first()->productVariant;
        $variant->update(['stock' => 6]); // simulate the sale already having deducted stock

        $payment = Payment::factory()->create(['order_id' => $order->id, 'provider' => 'fake', 'amount' => 2000, 'status' => PaymentStatus::Success]);

        $refund = ProcessRefund::run($payment, 2000);

        // ProcessRefund only reserves the refund and dispatches the gateway
        // call (IssueProviderRefund) — under the sync queue driver used in
        // tests it's already run by this point, but the returned $refund
        // instance itself is the one ProcessRefund built before dispatch,
        // so its in-memory status is still Pending; re-fetch for the result.
        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame(RefundStatus::Success, $refund->fresh()->status);
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'type' => StockMovementType::Return->value,
            'quantity' => 4,
        ]);
    }

    public function test_new_payment_driver_requires_no_action_changes(): void
    {
        // "Adding a provider" here is registering another driver name via the
        // same public PaymentManager::extend() point InitiatePayment already
        // uses through driverForChannel() — no Action code changes.
        $this->app->make(PaymentManager::class)->extend('another-fake', fn () => new FakePaymentGateway);
        config(['payments.channels.mobile_money' => 'another-fake']);

        $order = Order::factory()->create();
        $payment = InitiatePayment::run($order, 'mobile_money');

        $this->assertSame('another-fake', $payment->provider);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }
}
