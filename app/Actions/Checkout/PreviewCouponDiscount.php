<?php

/**
 * Validates a coupon code against the current cart and previews its discount, without applying anything.
 */

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Checkout\Support\ValidateCoupon;
use App\Exceptions\CouponAttemptsRateLimitedException;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\InvalidCouponException;
use App\Models\Cart;
use App\Models\Coupon;
use Illuminate\Support\Facades\RateLimiter;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * No row locking, no `CouponUsage` row, no persistence at all — a cart
 * isn't an order yet, so there's nothing to actually commit a coupon to.
 * This exists purely so a customer sees the discount before placing the
 * order, via the checkout page's "Apply" button. `CreateOrderFromCart`
 * still runs the authoritative, locked `ApplyCouponToOrder` at the actual
 * money boundary when the order is placed — this preview is optimistic
 * and can still be rejected there (e.g. someone else exhausts a shared
 * usage limit in between), same as a stock preview never guarantees the
 * final reservation succeeds.
 *
 * @throws InvalidCouponException when the code doesn't exist, is inactive,
 *                                expired, out of scope for the cart's items, or below its
 *                                minimum order amount
 * @throws CouponUsageLimitExceededException when usage_limit or
 *                                           usage_limit_per_user has already been reached
 * @throws CouponAttemptsRateLimitedException when this cart has attempted too many codes too quickly
 */
class PreviewCouponDiscount
{
    use AsAction;

    /**
     * @return array{coupon: Coupon, discount: int}
     */
    public function handle(Cart $cart, string $code, ?int $userId, ?string $guestEmail): array
    {
        // No other guard exists against brute-forcing coupon codes — the
        // checkout page's "Apply" button had no throttle at all, letting
        // a script try codes at unbounded rate against a single cart.
        $rateLimitKey = "coupon-preview:{$cart->id}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            throw new CouponAttemptsRateLimitedException;
        }

        RateLimiter::hit($rateLimitKey, 300);

        $coupon = Coupon::query()->where('code', $code)->first();

        if ($coupon === null) {
            throw new InvalidCouponException;
        }

        $items = $cart->items()->with('productVariant.product')->get();
        $subtotal = $items->sum(fn ($item) => $item->productVariant->price * $item->quantity);

        ValidateCoupon::check($coupon, $subtotal, $items, $userId, $guestEmail);

        return [
            'coupon' => $coupon,
            'discount' => ValidateCoupon::discount($coupon, $subtotal, $items),
        ];
    }
}
