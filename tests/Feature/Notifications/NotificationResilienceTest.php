<?php

/**
 * Covers that every queued Notification declares job-resilience config
 * ($tries/$timeout/$backoff) and logs via failed() when every retry is
 * exhausted, instead of vanishing silently — CLAUDE.md §15.
 */

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Notifications\CriticalHealthAlert;
use App\Notifications\LowStockAlert;
use App\Notifications\OrderPlaced;
use App\Notifications\ReservationsAtRiskAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class NotificationResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_notification_declares_resilience_config(): void
    {
        $notification = new OrderPlaced(Order::factory()->create());

        $this->assertSame(3, $notification->tries);
        $this->assertSame(30, $notification->timeout);
        $this->assertSame([10, 30, 60], $notification->backoff);
    }

    public function test_order_notification_logs_on_permanent_failure(): void
    {
        $order = Order::factory()->create();
        $notification = new OrderPlaced($order);

        Log::shouldReceive('error')
            ->once()
            ->with('App\Notifications\OrderPlaced failed permanently', [
                'order_id' => $order->id,
                'exception' => 'gateway unreachable',
            ]);

        $notification->failed(new RuntimeException('gateway unreachable'));
    }

    public function test_low_stock_alert_declares_resilience_config_and_logs_on_failure(): void
    {
        $variant = ProductVariant::factory()->create();
        $notification = new LowStockAlert($variant);

        $this->assertSame(3, $notification->tries);
        $this->assertSame(30, $notification->timeout);
        $this->assertSame([10, 30, 60], $notification->backoff);

        Log::shouldReceive('error')
            ->once()
            ->with('LowStockAlert failed permanently', [
                'product_variant_id' => $variant->id,
                'exception' => 'mail transport down',
            ]);

        $notification->failed(new RuntimeException('mail transport down'));
    }

    public function test_reservations_at_risk_alert_declares_resilience_config_and_logs_on_failure(): void
    {
        $variant = ProductVariant::factory()->create();
        $notification = new ReservationsAtRiskAlert($variant, [1, 2, 3]);

        $this->assertSame(3, $notification->tries);
        $this->assertSame(30, $notification->timeout);
        $this->assertSame([10, 30, 60], $notification->backoff);

        Log::shouldReceive('error')
            ->once()
            ->with('ReservationsAtRiskAlert failed permanently', [
                'product_variant_id' => $variant->id,
                'reservation_ids' => [1, 2, 3],
                'exception' => 'mail transport down',
            ]);

        $notification->failed(new RuntimeException('mail transport down'));
    }

    public function test_critical_health_alert_declares_resilience_config_and_logs_on_failure(): void
    {
        $notification = new CriticalHealthAlert(['DatabaseCheck', 'QueueCheck']);

        $this->assertSame(3, $notification->tries);
        $this->assertSame(30, $notification->timeout);
        $this->assertSame([10, 30, 60], $notification->backoff);

        Log::shouldReceive('error')
            ->once()
            ->with('CriticalHealthAlert failed permanently', [
                'failures' => ['DatabaseCheck', 'QueueCheck'],
                'exception' => 'mail transport down',
            ]);

        $notification->failed(new RuntimeException('mail transport down'));
    }
}
