<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\StockMovement;
use App\Models\User;
use App\Queries\DashboardMetricsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardMetricsQueryTest extends TestCase
{
    use RefreshDatabase;

    private DashboardMetricsQuery $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metrics = new DashboardMetricsQuery;
    }

    public function test_todays_sales_sums_only_successful_payments_created_today(): void
    {
        Payment::factory()->create(['status' => PaymentStatus::Success, 'amount' => 1000, 'created_at' => now()]);
        Payment::factory()->create(['status' => PaymentStatus::Pending, 'amount' => 5000, 'created_at' => now()]);
        Payment::factory()->create(['status' => PaymentStatus::Success, 'amount' => 9999, 'created_at' => now()->subDays(2)]);

        $this->assertSame(1000, $this->metrics->todaysSales());
    }

    public function test_monthly_revenue_sums_successful_payments_this_calendar_month(): void
    {
        Payment::factory()->create(['status' => PaymentStatus::Success, 'amount' => 2000, 'created_at' => now()]);
        Payment::factory()->create(['status' => PaymentStatus::Success, 'amount' => 5000, 'created_at' => now()->subMonths(2)]);

        $this->assertSame(2000, $this->metrics->monthlyRevenue());
    }

    public function test_todays_sales_subtracts_successful_refunds_issued_today(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Success, 'amount' => 5000, 'created_at' => now()]);
        Refund::factory()->create(['payment_id' => $payment->id, 'status' => RefundStatus::Success, 'amount' => 2000, 'created_at' => now()]);
        // A pending refund (not yet actually issued) shouldn't reduce revenue.
        Refund::factory()->create(['payment_id' => $payment->id, 'status' => RefundStatus::Pending, 'amount' => 1000, 'created_at' => now()]);

        $this->assertSame(3000, $this->metrics->todaysSales());
    }

    public function test_monthly_revenue_subtracts_successful_refunds_issued_this_month_even_for_an_older_payment(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Success, 'amount' => 5000, 'created_at' => now()->subMonths(2)]);
        Refund::factory()->create(['payment_id' => $payment->id, 'status' => RefundStatus::Success, 'amount' => 1500, 'created_at' => now()]);

        $this->assertSame(-1500, $this->metrics->monthlyRevenue());
    }

    public function test_pending_orders_count_only_counts_pending_status(): void
    {
        Order::factory()->create(['status' => OrderStatus::Pending]);
        Order::factory()->create(['status' => OrderStatus::Pending]);
        Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->assertSame(2, $this->metrics->pendingOrdersCount());
    }

    public function test_low_stock_count_reflects_variants_at_or_below_threshold(): void
    {
        ProductVariant::factory()->create(['stock' => 2, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->create(['stock' => 20, 'low_stock_threshold' => 5]);

        $this->assertSame(1, $this->metrics->lowStockCount());
    }

    public function test_new_customers_count_excludes_staff_and_older_accounts(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');

        User::factory()->create(['created_at' => now()]);
        $staff = User::factory()->create(['created_at' => now()]);
        $staff->assignRole(UserRole::Admin->value);
        User::factory()->create(['created_at' => now()->subMonths(3)]);

        $this->assertSame(1, $this->metrics->newCustomersCount());
    }

    public function test_top_products_ranks_by_quantity_sold_this_month(): void
    {
        $productA = Product::factory()->create();
        $variantA = ProductVariant::factory()->create(['product_id' => $productA->id]);
        $productB = Product::factory()->create();
        $variantB = ProductVariant::factory()->create(['product_id' => $productB->id]);

        $orderPaid = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderItem::factory()->create(['order_id' => $orderPaid->id, 'product_variant_id' => $variantA->id, 'quantity' => 10]);

        $orderPending = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->create(['order_id' => $orderPending->id, 'product_variant_id' => $variantB->id, 'quantity' => 999]);

        $results = $this->metrics->topProducts();

        $this->assertCount(1, $results);
        $this->assertSame($productA->id, $results->first()['product_id']);
        $this->assertSame(10, $results->first()['quantity_sold']);
    }

    public function test_top_products_subtracts_quantity_returned_via_a_refund_this_month(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'quantity' => 10]);

        $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Success]);
        $refund = Refund::factory()->create(['payment_id' => $payment->id, 'status' => RefundStatus::Success]);
        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'type' => StockMovementType::Return,
            'quantity' => 4,
            'reference_type' => Refund::class,
            'reference_id' => $refund->id,
        ]);

        $results = $this->metrics->topProducts();

        $this->assertSame(6, $results->first()['quantity_sold']);
    }
}
