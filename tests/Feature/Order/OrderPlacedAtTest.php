<?php

/**
 * Covers Order::placed_at / OrderStatusHistory::placed_at — customer-
 * facing timestamps converted to the store's configured display
 * timezone, rather than shown in raw UTC (which can display an order as
 * placed on the wrong calendar day for a store outside UTC).
 */

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPlacedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_placed_at_matches_created_at_under_the_default_utc_timezone(): void
    {
        $order = Order::factory()->create(['created_at' => '2026-08-19 23:30:00']);

        $this->assertSame('2026-08-19 23:30:00', $order->placed_at->format('Y-m-d H:i:s'));
    }

    public function test_placed_at_converts_to_the_configured_store_timezone(): void
    {
        StoreSetting::current()->update(['timezone' => 'America/New_York']);
        // 23:30 UTC on the 19th is 19:30 the same day in New York (UTC-4 in August).
        $order = Order::factory()->create(['created_at' => '2026-08-19 23:30:00']);

        $this->assertSame('2026-08-19 19:30:00', $order->placed_at->format('Y-m-d H:i:s'));
        $this->assertSame('America/New_York', $order->placed_at->getTimezone()->getName());
    }

    /**
     * The actual bug this closes: a late-night order in a timezone behind
     * UTC displaying on the wrong calendar day.
     */
    public function test_placed_at_can_shift_the_displayed_calendar_day(): void
    {
        StoreSetting::current()->update(['timezone' => 'America/Los_Angeles']);
        // 02:00 UTC on the 20th is 19:00 on the 19th in Los Angeles (UTC-7 in August).
        $order = Order::factory()->create(['created_at' => '2026-08-20 02:00:00']);

        $this->assertSame('2026-08-20', $order->created_at->format('Y-m-d'));
        $this->assertSame('2026-08-19', $order->placed_at->format('Y-m-d'));
    }

    public function test_order_status_history_placed_at_also_converts(): void
    {
        StoreSetting::current()->update(['timezone' => 'America/New_York']);
        $order = Order::factory()->create();
        $history = OrderStatusHistory::factory()->create(['order_id' => $order->id, 'created_at' => '2026-08-19 23:30:00']);

        $this->assertSame('2026-08-19 19:30:00', $history->placed_at->format('Y-m-d H:i:s'));
    }
}
