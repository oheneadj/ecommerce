<?php

/**
 * Covers InitiatePayment's handling of failures that happen before a
 * gateway response comes back — a missing provider config, or any other
 * exception thrown while resolving/calling the driver.
 */

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Actions\Payment\InitiatePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockReservationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentApiLog;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class InitiatePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function enableProvider(string $provider): void
    {
        DB::table('payment_provider_settings')->insert([
            'provider' => $provider,
            'enabled' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_missing_provider_api_key_does_not_throw_and_produces_a_failed_payment(): void
    {
        $this->enableProvider('moolre');
        config(['payments.providers.moolre.api_key' => null]);

        Log::shouldReceive('error')
            ->once()
            ->with('Payment initiation failed', \Mockery::on(fn (array $context): bool => $context['provider'] === 'moolre'
                && str_contains($context['exception'], 'Moolre payment API key is not configured')
            ));

        $order = Order::factory()->create();

        $payment = InitiatePayment::run($order, 'moolre');

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('moolre', $payment->provider);
        $this->assertSame(
            'Payment could not be started. Please try again or choose a different payment method.',
            $payment->metadata['error'],
        );
    }

    public function test_a_missing_provider_api_key_still_writes_an_api_log_entry(): void
    {
        $this->enableProvider('moolre');
        config(['payments.providers.moolre.api_key' => null]);
        Log::shouldReceive('error')->once();

        $order = Order::factory()->create();

        InitiatePayment::run($order, 'moolre');

        $log = PaymentApiLog::query()->where('order_id', $order->id)->sole();

        $this->assertSame('moolre', $log->provider);
        $this->assertSame('initiate', $log->action);
        $this->assertSame(500, $log->status_code);
        $this->assertStringContainsString('Moolre payment API key is not configured', $log->response_payload['error']);
    }

    public function test_an_unrecognized_provider_name_fails_gracefully_instead_of_throwing(): void
    {
        Log::shouldReceive('error')->once();

        $order = Order::factory()->create();

        $payment = InitiatePayment::run($order, 'carrier_pigeon');

        $this->assertSame(PaymentStatus::Failed, $payment->status);
    }

    public function test_a_recognized_but_disabled_provider_fails_gracefully_instead_of_throwing(): void
    {
        // Not enabled — recognized by PaymentManager (it has a real driver
        // and config entry) but not currently offered at checkout.
        Log::shouldReceive('error')->once();

        $order = Order::factory()->create();

        $payment = InitiatePayment::run($order, 'moolre');

        $this->assertSame(PaymentStatus::Failed, $payment->status);
    }

    /**
     * A $0 order (a free product, or a coupon/tax/shipping combination
     * that zeroes the total) previously had no path to completion — every
     * gateway rejects a zero-amount charge, so InitiatePayment always
     * produced a Failed payment and the order could never be paid for.
     */
    public function test_a_zero_total_order_is_settled_without_calling_any_gateway(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 0, 'stock' => 5]);
        $order = Order::factory()->create(['grand_total' => 0, 'status' => OrderStatus::Pending]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);
        StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'status' => StockReservationStatus::Active,
        ]);

        $payment = InitiatePayment::run($order, 'paystack');

        $this->assertSame(PaymentStatus::Success, $payment->status);
        $this->assertSame('free', $payment->provider);
        $this->assertSame(0, $payment->amount);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(4, $variant->fresh()->stock);
    }

    public function test_a_zero_total_order_never_logs_a_payment_api_call(): void
    {
        $order = Order::factory()->create(['grand_total' => 0]);

        InitiatePayment::run($order, 'paystack');

        $this->assertSame(0, PaymentApiLog::query()->where('order_id', $order->id)->count());
    }

    public function test_a_zero_total_order_is_idempotent_per_order(): void
    {
        $order = Order::factory()->create(['grand_total' => 0]);

        $first = InitiatePayment::run($order, 'paystack');
        $second = InitiatePayment::run($order, 'paystack');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $order->payments()->count());
    }
}
