<?php

/**
 * Covers PreviewCouponDiscount — validating a coupon against a cart
 * (before an order exists) and calculating the discount it would give,
 * without persisting anything.
 */

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Checkout\PreviewCouponDiscount;
use App\Enums\CouponType;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\InvalidCouponException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewCouponDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function cartWithItem(int $price = 10000): Cart
    {
        $variant = ProductVariant::factory()->create(['price' => $price, 'stock' => 5]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);

        return $cart;
    }

    public function test_it_previews_a_fixed_discount_without_persisting_anything(): void
    {
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 500]);
        $cart = $this->cartWithItem();

        $result = PreviewCouponDiscount::run($cart, $coupon->code, null, 'guest@example.com');

        $this->assertSame(500, $result['discount']);
        $this->assertSame($coupon->id, $result['coupon']->id);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertNull($cart->fresh()->order);
    }

    public function test_it_previews_a_percentage_discount(): void
    {
        $coupon = Coupon::factory()->create(['type' => CouponType::Percentage, 'value' => 10]);
        $cart = $this->cartWithItem(10000);

        $result = PreviewCouponDiscount::run($cart, $coupon->code, null, 'guest@example.com');

        $this->assertSame(1000, $result['discount']);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $cart = $this->cartWithItem();

        $this->expectException(InvalidCouponException::class);

        PreviewCouponDiscount::run($cart, 'DOES-NOT-EXIST', null, 'guest@example.com');
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $coupon = Coupon::factory()->create(['expires_at' => now()->subDay()]);
        $cart = $this->cartWithItem();

        $this->expectException(InvalidCouponException::class);

        PreviewCouponDiscount::run($cart, $coupon->code, null, 'guest@example.com');
    }

    public function test_a_coupon_below_its_minimum_order_amount_is_rejected(): void
    {
        $coupon = Coupon::factory()->create(['min_order_amount' => 50000]);
        $cart = $this->cartWithItem(10000);

        $this->expectException(InvalidCouponException::class);

        PreviewCouponDiscount::run($cart, $coupon->code, null, 'guest@example.com');
    }

    public function test_a_coupon_at_its_usage_limit_is_rejected(): void
    {
        $coupon = Coupon::factory()->create(['usage_limit' => 1]);
        CouponUsage::factory()->create(['coupon_id' => $coupon->id]);
        $cart = $this->cartWithItem();

        $this->expectException(CouponUsageLimitExceededException::class);

        PreviewCouponDiscount::run($cart, $coupon->code, null, 'guest@example.com');
    }
}
