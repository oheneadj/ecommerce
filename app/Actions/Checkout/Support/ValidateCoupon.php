<?php

/**
 * Shared coupon validation and discount calculation, used both to
 * preview a coupon at checkout and to actually apply one to an order.
 */

declare(strict_types=1);

namespace App\Actions\Checkout\Support;

use App\Enums\CouponType;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use Illuminate\Support\Collection;

/**
 * Deliberately not tied to `Order` or `Cart` — takes a plain subtotal and
 * item collection (cart items or order items both expose `productVariant`
 * and `quantity`), so `App\Actions\Checkout\PreviewCouponDiscount` (cart,
 * before an order exists) and `App\Actions\Checkout\ApplyCouponToOrder`
 * (order, at the moment it's actually applied) enforce exactly the same
 * rules from one definition instead of two copies drifting apart.
 */
class ValidateCoupon
{
    /**
     * @param  Collection<int, mixed>  $items
     *
     * @throws InvalidCouponException when the coupon is inactive, expired,
     *                                out of scope for the given items, or below its minimum order amount
     * @throws CouponUsageLimitExceededException when usage_limit or
     *                                           usage_limit_per_user has already been reached
     */
    public static function check(Coupon $coupon, int $subtotal, Collection $items, ?int $userId, ?string $guestEmail): void
    {
        if (! $coupon->active) {
            throw new InvalidCouponException;
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
            throw new InvalidCouponException('This coupon has expired.');
        }

        if ($coupon->min_order_amount !== null && $subtotal < $coupon->min_order_amount) {
            throw new InvalidCouponException('This order does not meet the coupon\'s minimum amount.');
        }

        if (! self::isInScope($coupon, $items)) {
            throw new InvalidCouponException('This coupon does not apply to the items in this order.');
        }

        if ($coupon->usage_limit !== null && $coupon->usages()->count() >= $coupon->usage_limit) {
            throw new CouponUsageLimitExceededException;
        }

        if ($coupon->usage_limit_per_user !== null && self::userUsageCount($coupon, $userId, $guestEmail) >= $coupon->usage_limit_per_user) {
            throw new CouponUsageLimitExceededException('You have already used this coupon the maximum number of times.');
        }
    }

    /**
     * Fixed and Percentage are both clamped to [0, subtotal] — a coupon's
     * discount must never exceed what the order is worth, or go negative
     * and increase the total instead.
     */
    public static function discount(Coupon $coupon, int $subtotal): int
    {
        $discount = match ($coupon->type) {
            CouponType::Fixed => $coupon->value ?? 0,
            CouponType::Percentage => (int) round($subtotal * ($coupon->value ?? 0) / 100),
            CouponType::FreeShipping => 0,
        };

        return max(0, min($discount, $subtotal));
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private static function isInScope(Coupon $coupon, Collection $items): bool
    {
        if (! $coupon->isScoped()) {
            return true;
        }

        $productIds = $coupon->products()->pluck('products.id');
        $categoryIds = $coupon->categories()->pluck('categories.id');

        return $items->some(function ($item) use ($productIds, $categoryIds): bool {
            $product = $item->productVariant->product;

            return $productIds->contains($product->id) || $categoryIds->contains($product->category_id);
        });
    }

    private static function userUsageCount(Coupon $coupon, ?int $userId, ?string $guestEmail): int
    {
        $query = $coupon->usages();

        return $userId !== null
            ? $query->where('user_id', $userId)->count()
            : $query->where('guest_email', $guestEmail)->count();
    }
}
