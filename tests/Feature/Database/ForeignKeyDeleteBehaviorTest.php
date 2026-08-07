<?php

/**
 * Covers that every foreign key's delete behavior is exactly what it's
 * supposed to be — restrict (a clean, catchable QueryException instead of
 * an admin action silently corrupting data) or cascade (child rows removed
 * along with the parent) — for every relationship that previously had no
 * explicit behavior at all.
 */

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\WishlistItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForeignKeyDeleteBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_category_with_products_is_restricted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->expectException(QueryException::class);

        $category->delete();
    }

    public function test_deleting_a_product_cascades_to_its_variants(): void
    {
        // Product is soft-deletable — forceDelete() to actually exercise
        // the DB-level FK behavior, which a plain delete() never touches.
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $product->forceDelete();

        $this->assertNull(ProductVariant::withTrashed()->find($variant->id));
    }

    public function test_deleting_an_order_with_a_payment_is_restricted(): void
    {
        $order = Order::factory()->create();
        Payment::factory()->create(['order_id' => $order->id]);

        $this->expectException(QueryException::class);

        $order->delete();
    }

    public function test_deleting_a_payment_with_a_refund_is_restricted(): void
    {
        $payment = Payment::factory()->create();
        Refund::factory()->create(['payment_id' => $payment->id]);

        $this->expectException(QueryException::class);

        $payment->delete();
    }

    public function test_deleting_an_order_with_a_shipment_is_restricted(): void
    {
        $order = Order::factory()->create();
        Shipment::factory()->create(['order_id' => $order->id]);

        $this->expectException(QueryException::class);

        $order->delete();
    }

    public function test_deleting_a_shipping_method_with_shipments_is_restricted(): void
    {
        $method = ShippingMethod::factory()->create();
        Shipment::factory()->create(['shipping_method_id' => $method->id]);

        $this->expectException(QueryException::class);

        $method->delete();
    }

    public function test_deleting_a_coupon_with_usage_records_is_restricted(): void
    {
        $coupon = Coupon::factory()->create();
        CouponUsage::factory()->create(['coupon_id' => $coupon->id]);

        $this->expectException(QueryException::class);

        $coupon->delete();
    }

    public function test_deleting_an_order_with_coupon_usage_records_is_restricted(): void
    {
        $order = Order::factory()->create();
        CouponUsage::factory()->create(['order_id' => $order->id]);

        $this->expectException(QueryException::class);

        $order->delete();
    }

    public function test_deleting_a_variant_with_stock_movement_history_is_restricted(): void
    {
        $variant = ProductVariant::factory()->create();
        StockMovement::factory()->create(['product_variant_id' => $variant->id]);

        $this->expectException(QueryException::class);

        $variant->forceDelete();
    }

    public function test_deleting_a_product_with_reviews_is_restricted(): void
    {
        // Product is soft-deletable — forceDelete() to actually exercise
        // the DB-level FK behavior, which a plain delete() never touches.
        $product = Product::factory()->create();
        Review::factory()->create(['product_id' => $product->id]);

        $this->expectException(QueryException::class);

        $product->forceDelete();
    }

    public function test_deleting_a_variant_wishlisted_by_someone_cascades_the_wishlist_entry(): void
    {
        $variant = ProductVariant::factory()->create();
        $wishlistItem = WishlistItem::factory()->create(['product_variant_id' => $variant->id]);

        $variant->forceDelete();

        $this->assertNull(WishlistItem::query()->find($wishlistItem->id));
    }

    public function test_deleting_an_order_item_with_a_review_nulls_the_reviews_reference_instead_of_blocking(): void
    {
        // Different from every other relationship in this file — the
        // column is already nullable (freed on review delete/resubmit),
        // so nullOnDelete() is the only behavior that makes sense here.
        $orderItem = OrderItem::factory()->create();
        $review = Review::factory()->create(['order_item_id' => $orderItem->id]);

        $orderItem->delete();

        $this->assertNull($review->fresh()->order_item_id);
    }

    public function test_deleting_a_variant_with_stock_reservation_history_is_restricted(): void
    {
        $variant = ProductVariant::factory()->create();
        StockReservation::factory()->create(['product_variant_id' => $variant->id]);

        $this->expectException(QueryException::class);

        $variant->forceDelete();
    }
}
