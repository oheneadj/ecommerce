<?php

/**
 * Covers InitiatePayment's handling of failures that happen before a
 * gateway response comes back — a missing provider config, or any other
 * exception thrown while resolving/calling the driver.
 */

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Actions\Payment\InitiatePayment;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentApiLog;
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
}
