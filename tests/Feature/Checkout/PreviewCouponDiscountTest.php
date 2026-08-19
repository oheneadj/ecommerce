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
use App\Exceptions\CouponAttemptsRateLimitedException;
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

    /**
     * No other guard existed against brute-forcing coupon codes — the
     * checkout page's "Apply" button had no throttle at all.
     */
    public function test_too_many_attempts_against_one_cart_are_rate_limited(): void
    {
        $cart = $this->cartWithItem();

        for ($i = 0; $i < 10; $i++) {
            try {
                PreviewCouponDiscount::run($cart, 'DOES-NOT-EXIST', null, 'guest@example.com');
            } catch (InvalidCouponException) {
                // expected — the codes themselves are invalid, only the count matters here
            }
        }

        $this->expectException(CouponAttemptsRateLimitedException::class);

        PreviewCouponDiscount::run($cart, 'DOES-NOT-EXIST', null, 'guest@example.com');
    }

    public function test_the_coupon_rate_limit_is_scoped_per_cart(): void
    {
        $cart = $this->cartWithItem();
        $otherCart = $this->cartWithItem();
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 500]);

        for ($i = 0; $i < 10; $i++) {
            try {
                PreviewCouponDiscount::run($cart, 'DOES-NOT-EXIST', null, 'guest@example.com');
            } catch (InvalidCouponException) {
                // expected
            }
        }

        // A different cart is unaffected by the first cart's rate limit.
        $result = PreviewCouponDiscount::run($otherCart, $coupon->code, null, 'guest@example.com');

        $this->assertSame(500, $result['discount']);
    }
}
