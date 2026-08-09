<?php

/**
 * Covers the Tier 3 (data integrity) checks and the nightly
 * RunIntegrityChecks Action that stores their results
 * (docs/TASK-system-health-checks.md Step 4).
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Catalog\DeleteProduct;
use App\Actions\Checkout\CreateOrderFromCart;
use App\Actions\Health\RunIntegrityChecks;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Review\SubmitReview;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\HealthChecks\Integrity\NoOrdersWithoutItems;
use App\HealthChecks\Integrity\NoProductsWithoutVariants;
use App\HealthChecks\Integrity\NoRefundExceedsItsPayment;
use App\HealthChecks\Integrity\NoReviewsWithoutVerifiedPurchase;
use App\HealthChecks\Integrity\NoSoftDeletedRecordHoldsOriginalUniqueValue;
use App\HealthChecks\Integrity\NoUsersWithoutIdentifier;
use App\HealthChecks\Integrity\StatusColumnsContainValidValues;
use App\HealthChecks\Integrity\StockCacheMatchesMovements;
use App\Models\Address;
use App\Models\Cart;
use App\Models\IntegrityCheckResult;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrityChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_cache_matches_movements_is_clean_when_created_through_record_stock_movement(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        RecordStockMovement::run($variant, StockMovementType::Restock, 10);

        $outcome = (new StockCacheMatchesMovements)->run();

        $this->assertSame(0, $outcome->violationCount);
    }

    public function test_stock_cache_matches_movements_flags_a_variant_whose_cache_drifted(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        RecordStockMovement::run($variant, StockMovementType::Restock, 10);
        DB::table('product_variants')->where('id', $variant->id)->update(['stock' => 999]);

        $outcome = (new StockCacheMatchesMovements)->run();

        $this->assertSame(1, $outcome->violationCount);
        $this->assertSame([$variant->id], $outcome->sampleIds);
    }

    public function test_no_users_without_identifier_flags_a_user_with_none(): void
    {
        $user = User::factory()->create(['phone' => null, 'email' => null, 'google_id' => null]);

        $outcome = (new NoUsersWithoutIdentifier)->run();

        $this->assertSame([$user->id], $outcome->sampleIds);
    }

    public function test_no_users_without_identifier_is_clean_when_every_user_has_one(): void
    {
        User::factory()->create(['phone' => '0244000000']);

        $outcome = (new NoUsersWithoutIdentifier)->run();

        $this->assertSame(0, $outcome->violationCount);
    }

    public function test_no_refund_exceeds_its_payment_flags_an_oversized_refund(): void
    {
        $payment = Payment::factory()->create(['amount' => 1000]);
        $refund = Refund::factory()->create(['payment_id' => $payment->id, 'amount' => 2000]);

        $outcome = (new NoRefundExceedsItsPayment)->run();

        $this->assertSame([$refund->id], $outcome->sampleIds);
    }

    public function test_no_refund_exceeds_its_payment_is_clean_when_refunds_are_within_bounds(): void
    {
        $payment = Payment::factory()->create(['amount' => 1000]);
        Refund::factory()->create(['payment_id' => $payment->id, 'amount' => 500]);

        $outcome = (new NoRefundExceedsItsPayment)->run();

        $this->assertSame(0, $outcome->violationCount);
    }

    public function test_no_orders_without_items_flags_an_order_with_none(): void
    {
        $order = Order::factory()->create();

        $outcome = (new NoOrdersWithoutItems)->run();

        $this->assertSame([$order->id], $outcome->sampleIds);
    }

    public function test_no_orders_without_items_is_clean_for_a_real_checkout(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);
        CreateOrderFromCart::run($cart, $address);

        $outcome = (new NoOrdersWithoutItems)->run();

        $this->assertSame(0, $outcome->violationCount);
    }

    public function test_no_products_without_variants_flags_an_active_product_with_none(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);

        $outcome = (new NoProductsWithoutVariants)->run();

        $this->assertSame([$product->id], $outcome->sampleIds);
    }

    public function test_no_products_without_variants_exempts_archived_products(): void
    {
        Product::factory()->create(['status' => ProductStatus::Archived]);

        $outcome = (new NoProductsWithoutVariants)->run();

        $this->assertSame(0, $outcome->violationCount);
    }

    public function test_no_reviews_without_verified_purchase_is_clean_for_a_real_review(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Delivered]);
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id]);
        SubmitReview::run($user, $orderItem, 5, 'Great product.');

        $outcome = (new NoReviewsWithoutVerifiedPurchase)->run();

        $this->assertSame(0, $outcome->violationCount);
    }

    public function test_no_reviews_without_verified_purchase_flags_a_review_against_a_pending_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id]);
        $review = Review::factory()->create(['user_id' => $user->id, 'order_item_id' => $orderItem->id, 'product_id' => $orderItem->productVariant->product_id]);

        $outcome = (new NoReviewsWithoutVerifiedPurchase)->run();

        $this->assertSame([$review->id], $outcome->sampleIds);
    }

    public function test_status_columns_contain_valid_values_is_clean_for_normal_data(): void
    {
        Order::factory()->create();

        $outcome = (new StatusColumnsContainValidValues)->run();

        $this->assertSame(0, $outcome->violationCount);
    }

    public function test_status_columns_contain_valid_values_flags_a_raw_invalid_value(): void
    {
        $order = Order::factory()->create();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'not-a-real-status']);

        $outcome = (new StatusColumnsContainValidValues)->run();

        $this->assertContains($order->id, $outcome->sampleIds);
    }

    public function test_no_soft_deleted_record_holds_original_unique_value_is_clean_via_delete_product(): void
    {
        $product = Product::factory()->create();
        DeleteProduct::run($product);

        $outcome = (new NoSoftDeletedRecordHoldsOriginalUniqueValue)->run();

        $this->assertSame(0, $outcome->violationCount);
    }

    public function test_no_soft_deleted_record_holds_original_unique_value_flags_a_bypassed_delete(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $outcome = (new NoSoftDeletedRecordHoldsOriginalUniqueValue)->run();

        $this->assertSame([$product->id], $outcome->sampleIds);
    }

    public function test_run_integrity_checks_stores_one_row_per_check_and_updates_in_place_on_rerun(): void
    {
        RunIntegrityChecks::run();
        $firstRunCount = IntegrityCheckResult::query()->count();

        RunIntegrityChecks::run();
        $secondRunCount = IntegrityCheckResult::query()->count();

        $this->assertSame(8, $firstRunCount);
        $this->assertSame(8, $secondRunCount);
    }

    public function test_run_integrity_checks_marks_a_critical_violation_as_failed_and_a_warning_one_as_warning(): void
    {
        Order::factory()->create();
        Product::factory()->create(['status' => ProductStatus::Active]);

        RunIntegrityChecks::run();

        $this->assertSame('failed', IntegrityCheckResult::query()->where('check_name', 'No orders without items')->value('status'));
        $this->assertSame('warning', IntegrityCheckResult::query()->where('check_name', 'No products without variants')->value('status'));
    }
}
