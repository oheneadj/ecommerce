<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Checkout\ApplyCouponToOrder;
use App\Actions\Checkout\CreateOrderFromCart;
use App\Enums\CouponType;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checking_out_an_empty_cart_is_rejected(): void
    {
        $cart = Cart::factory()->create();
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        $this->expectException(EmptyCartException::class);

        CreateOrderFromCart::run($cart, $address);
    }

    public function test_stock_is_reserved_not_deducted_at_checkout(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 3);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        CreateOrderFromCart::run($cart, $address);

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(3, StockReservation::query()->where('product_variant_id', $variant->id)->sum('quantity'));
    }

    public function test_order_item_price_snapshot_is_taken_at_order_creation_not_cart_add_time(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 1000, 'sku' => 'ORIG-SKU']);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        // Price changes after the item was added to the cart but before checkout.
        $variant->update(['price' => 2500]);

        $order = CreateOrderFromCart::run($cart, $address);

        $this->assertSame(2500, $order->items()->first()->unit_price);
        $this->assertSame('ORIG-SKU', $order->items()->first()->item_snapshot['sku']);
    }

    public function test_past_order_is_unaffected_by_a_later_product_edit(): void
    {
        $product = Product::factory()->create(['name' => 'Original Name']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SKU-1']);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        $order = CreateOrderFromCart::run($cart, $address);

        $product->update(['name' => 'Renamed Product']);

        $this->assertSame('Original Name', $order->items()->first()->item_snapshot['product_name']);
    }

    public function test_duplicate_checkout_submission_does_not_create_a_second_order(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        $first = CreateOrderFromCart::run($cart, $address);
        $second = CreateOrderFromCart::run($cart->fresh(), $address);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_guest_order_is_never_auto_attached_to_an_account_on_matching_email(): void
    {
        $existingUser = User::factory()->create(['email' => 'shopper@example.com']);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $guestCart = Cart::factory()->create(['user_id' => null, 'session_id' => 'guest-session']);
        AddItemToCart::run($guestCart, $variant, 1);
        $address = Address::factory()->create(['user_id' => null]);

        $order = CreateOrderFromCart::run($guestCart, $address, guestEmail: 'shopper@example.com');

        $this->assertNull($order->user_id);
        $this->assertSame('shopper@example.com', $order->guest_email);
        $this->assertNotSame($existingUser->id, $order->user_id);
    }

    public function test_order_number_is_generated_on_creation(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        $order = CreateOrderFromCart::run($cart, $address);

        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6}$/', $order->order_number);
    }

    public function test_checkout_applies_the_store_wide_tax_rate_to_the_subtotal(): void
    {
        StoreSetting::current()->update(['tax_rate' => 15]);

        $variant = ProductVariant::factory()->create(['stock' => 10, 'price' => 1000]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        $order = CreateOrderFromCart::run($cart, $address);

        $this->assertSame(150, $order->tax_total);
        $this->assertSame(1150, $order->grand_total);
    }

    public function test_checkout_with_a_zero_tax_rate_charges_no_tax(): void
    {
        StoreSetting::current()->update(['tax_rate' => 0]);

        $variant = ProductVariant::factory()->create(['stock' => 10, 'price' => 1000]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        $order = CreateOrderFromCart::run($cart, $address);

        $this->assertSame(0, $order->tax_total);
        $this->assertSame(1000, $order->grand_total);
    }

    public function test_a_coupon_discount_does_not_reduce_the_tax_already_computed_on_the_subtotal(): void
    {
        StoreSetting::current()->update(['tax_rate' => 10]);

        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 500]);
        $variant = ProductVariant::factory()->create(['stock' => 10, 'price' => 1000]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        $order = CreateOrderFromCart::run($cart, $address, couponCode: $coupon->code);

        $this->assertSame(100, $order->fresh()->tax_total);
        $this->assertSame(600, $order->fresh()->grand_total);
    }

    public function test_the_order_number_sequence_resets_at_the_start_of_a_new_year_rather_than_continuing_an_all_time_count(): void
    {
        $this->travelTo('2020-06-15');
        Order::factory()->count(3)->create();

        $this->travelTo('2026-01-01');
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        $order = CreateOrderFromCart::run($cart, $address);

        $this->assertSame('ORD-2026-000001', $order->order_number);
    }

    public function test_concurrent_checkout_on_last_unit_prevents_overselling(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 1]);

        $carts = collect(range(1, 5))->map(function () use ($variant) {
            $cart = Cart::factory()->create();
            AddItemToCart::run($cart, $variant, 1);

            return $cart;
        });

        $succeeded = 0;
        $rejected = 0;

        foreach ($carts as $cart) {
            $address = Address::factory()->create(['user_id' => $cart->user_id]);

            try {
                CreateOrderFromCart::run($cart, $address);
                $succeeded++;
            } catch (InsufficientStockException) {
                $rejected++;
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(4, $rejected);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $coupon = Coupon::factory()->create(['expires_at' => now()->subDay()]);
        $order = $this->createPendingOrderWithSubtotal(1000);

        $this->expectException(InvalidCouponException::class);

        ApplyCouponToOrder::run($order, $coupon->code);
    }

    public function test_coupon_below_minimum_order_amount_is_rejected(): void
    {
        $coupon = Coupon::factory()->create(['min_order_amount' => 5000]);
        $order = $this->createPendingOrderWithSubtotal(1000);

        $this->expectException(InvalidCouponException::class);

        ApplyCouponToOrder::run($order, $coupon->code);
    }

    public function test_coupon_rejects_out_of_scope_products(): void
    {
        $scopedCategory = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        $coupon = Coupon::factory()->create();
        $coupon->categories()->attach($scopedCategory->id);

        $product = Product::factory()->create(['category_id' => $otherCategory->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $order = $this->createPendingOrderWithVariant($variant, 1);

        $this->expectException(InvalidCouponException::class);

        ApplyCouponToOrder::run($order, $coupon->code);
    }

    public function test_coupon_applies_when_product_is_in_scope(): void
    {
        $category = Category::factory()->create();
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 500]);
        $coupon->categories()->attach($category->id);

        $product = Product::factory()->create(['category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 2000]);

        $order = $this->createPendingOrderWithVariant($variant, 1);

        ApplyCouponToOrder::run($order, $coupon->code);

        $this->assertSame(500, $order->fresh()->discount_total);
    }

    public function test_a_fixed_discount_larger_than_the_subtotal_is_capped_at_the_subtotal(): void
    {
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 5000]);
        $order = $this->createPendingOrderWithSubtotal(1000);

        ApplyCouponToOrder::run($order, $coupon->code);

        $this->assertSame(1000, $order->fresh()->discount_total);
    }

    public function test_a_percentage_discount_over_100_percent_is_capped_at_the_subtotal(): void
    {
        // The Filament form already rejects a value over 100, but this is
        // the actual enforcement boundary — a coupon's discount must never
        // exceed the order's subtotal regardless of what value made it
        // into the database.
        $coupon = Coupon::factory()->create(['type' => CouponType::Percentage, 'value' => 150]);
        $order = $this->createPendingOrderWithSubtotal(1000);

        ApplyCouponToOrder::run($order, $coupon->code);

        $this->assertSame(1000, $order->fresh()->discount_total);
    }

    public function test_coupon_usage_limit_counts_actual_usage_rows(): void
    {
        $coupon = Coupon::factory()->create(['usage_limit' => 1]);
        CouponUsage::factory()->create(['coupon_id' => $coupon->id]);

        $order = $this->createPendingOrderWithSubtotal(1000);

        $this->expectException(CouponUsageLimitExceededException::class);

        ApplyCouponToOrder::run($order, $coupon->code);
    }

    public function test_guest_coupon_usage_limit_is_enforced_by_email(): void
    {
        $coupon = Coupon::factory()->create(['usage_limit_per_user' => 1]);
        CouponUsage::factory()->create([
            'coupon_id' => $coupon->id,
            'user_id' => null,
            'guest_email' => 'repeat@example.com',
        ]);

        $order = $this->createPendingOrderWithSubtotal(1000, guestEmail: 'repeat@example.com');

        $this->expectException(CouponUsageLimitExceededException::class);

        ApplyCouponToOrder::run($order, $coupon->code);
    }

    public function test_concurrent_coupon_use_respects_usage_limit(): void
    {
        $coupon = Coupon::factory()->create(['usage_limit' => 1]);

        $succeeded = 0;
        $rejected = 0;

        for ($i = 0; $i < 5; $i++) {
            $order = $this->createPendingOrderWithSubtotal(1000);

            try {
                ApplyCouponToOrder::run($order, $coupon->code);
                $succeeded++;
            } catch (CouponUsageLimitExceededException) {
                $rejected++;
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(4, $rejected);
        $this->assertSame(1, CouponUsage::query()->where('coupon_id', $coupon->id)->count());
    }

    public function test_free_shipping_coupon_zeroes_shipping_without_touching_discount(): void
    {
        $coupon = Coupon::factory()->create(['type' => CouponType::FreeShipping, 'value' => null]);
        $order = $this->createPendingOrderWithSubtotal(1000);
        $order->update(['shipping_total' => 300, 'grand_total' => 1300]);

        ApplyCouponToOrder::run($order, $coupon->code);

        $order->refresh();
        $this->assertSame(0, $order->shipping_total);
        $this->assertSame(0, $order->discount_total);
        $this->assertSame(1000, $order->grand_total);
    }

    public function test_stock_cache_still_matches_movements_after_a_full_checkout(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 2);
        $address = Address::factory()->create(['user_id' => $cart->user_id]);

        CreateOrderFromCart::run($cart, $address);

        // Checkout only reserves — it never mutates the cached stock total.
        $this->assertSame(5, $variant->fresh()->stock);
    }

    private function createPendingOrderWithSubtotal(int $subtotal, ?string $guestEmail = null): Order
    {
        $address = Address::factory()->create();

        return Order::factory()->create([
            'address_id' => $address->id,
            'user_id' => $guestEmail === null ? User::factory() : null,
            'guest_email' => $guestEmail,
            'subtotal' => $subtotal,
            'grand_total' => $subtotal,
        ]);
    }

    private function createPendingOrderWithVariant(ProductVariant $variant, int $quantity): Order
    {
        $order = $this->createPendingOrderWithSubtotal($variant->price * $quantity);

        $order->items()->create([
            'product_variant_id' => $variant->id,
            'item_snapshot' => ['product_name' => $variant->product->name, 'sku' => $variant->sku],
            'unit_price' => $variant->price,
            'quantity' => $quantity,
        ]);

        return $order;
    }
}
