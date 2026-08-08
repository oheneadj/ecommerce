<?php

/**
 * Covers Filament sidebar navigation badges — Products shows the count of
 * active low-stock variants (same definition DashboardMetricsQuery/
 * LowStockVariantsWidget use), Orders shows the count of pending orders
 * (same definition DashboardMetricsQuery::pendingOrdersCount() uses).
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\VariantStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_badge_is_null_when_nothing_is_low_on_stock(): void
    {
        ProductVariant::factory()->create(['status' => VariantStatus::Active, 'stock' => 1000, 'low_stock_threshold' => 5]);

        $this->assertNull(ProductResource::getNavigationBadge());
    }

    public function test_products_badge_counts_active_low_stock_variants(): void
    {
        ProductVariant::factory()->create(['status' => VariantStatus::Active, 'stock' => 2, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->create(['status' => VariantStatus::Active, 'stock' => 1, 'low_stock_threshold' => 5]);
        // Inactive and healthy-stock variants must not be counted.
        ProductVariant::factory()->create(['status' => VariantStatus::Inactive, 'stock' => 0, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->create(['status' => VariantStatus::Active, 'stock' => 1000, 'low_stock_threshold' => 5]);

        $this->assertSame('2', ProductResource::getNavigationBadge());
        $this->assertSame('warning', ProductResource::getNavigationBadgeColor());
    }

    public function test_orders_badge_is_null_when_nothing_is_pending(): void
    {
        Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->assertNull(OrderResource::getNavigationBadge());
    }

    public function test_orders_badge_counts_pending_orders_only(): void
    {
        Order::factory()->count(3)->create(['status' => OrderStatus::Pending]);
        Order::factory()->create(['status' => OrderStatus::Paid]);
        Order::factory()->create(['status' => OrderStatus::Cancelled]);

        $this->assertSame('3', OrderResource::getNavigationBadge());
        $this->assertSame('info', OrderResource::getNavigationBadgeColor());
    }
}
