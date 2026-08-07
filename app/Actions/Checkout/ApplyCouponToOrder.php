<?php

/**
 * Validates and applies a coupon code to an order, recording its usage.
 */

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Enums\CouponType;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One of only two Actions in the whole system that requires row-level
 * locking (the other is ReserveStockForOrder) — coupon usage is a finite,
 * contested resource exactly like stock. The Coupon row is locked, actual
 * `coupon_usages` rows are counted (never a cached counter column — a
 * counter can drift, rows cannot), and the usage limits are enforced
 * before the usage row is inserted, all inside the one locked transaction.
 *
 * @throws InvalidCouponException when the code doesn't exist, is inactive,
 *                                expired, out of scope for the order's items, or below its
 *                                minimum order amount
 * @throws CouponUsageLimitExceededException when usage_limit or
 *                                           usage_limit_per_user has already been reached
 */
class ApplyCouponToOrder
{
    use AsAction;

    public function handle(Order $order, string $code): CouponUsage
    {
        return DB::transaction(function () use ($order, $code): CouponUsage {
            $coupon = Coupon::query()->where('code', $code)->lockForUpdate()->first();

            if ($coupon === null || ! $coupon->active) {
                throw new InvalidCouponException;
            }

            if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
                throw new InvalidCouponException('This coupon has expired.');
            }

            if ($coupon->min_order_amount !== null && $order->subtotal < $coupon->min_order_amount) {
                throw new InvalidCouponException('This order does not meet the coupon\'s minimum amount.');
            }

            if (! $this->isInScope($coupon, $order)) {
                throw new InvalidCouponException('This coupon does not apply to the items in this order.');
            }

            if ($coupon->usage_limit !== null && $coupon->usages()->count() >= $coupon->usage_limit) {
                throw new CouponUsageLimitExceededException;
            }

            if ($coupon->usage_limit_per_user !== null && $this->userUsageCount($coupon, $order) >= $coupon->usage_limit_per_user) {
                throw new CouponUsageLimitExceededException('You have already used this coupon the maximum number of times.');
            }

            $discount = $this->calculateDiscount($coupon, $order);
            $shippingTotal = $coupon->type === CouponType::FreeShipping ? 0 : $order->shipping_total;

            $order->update([
                'coupon_id' => $coupon->id,
                'discount_total' => $discount,
                'shipping_total' => $shippingTotal,
                'grand_total' => max(0, $order->subtotal - $discount + $order->tax_total + $shippingTotal),
            ]);

            return CouponUsage::query()->create([
                'coupon_id' => $coupon->id,
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'guest_email' => $order->user_id === null ? $order->guest_email : null,
            ]);
        }, 3);
    }

    private function isInScope(Coupon $coupon, Order $order): bool
    {
        if (! $coupon->isScoped()) {
            return true;
        }

        $productIds = $coupon->products()->pluck('products.id');
        $categoryIds = $coupon->categories()->pluck('categories.id');

        return $order->items()
            ->with('productVariant.product')
            ->get()
            ->some(function ($item) use ($productIds, $categoryIds): bool {
                $product = $item->productVariant->product;

                return $productIds->contains($product->id) || $categoryIds->contains($product->category_id);
            });
    }

    private function userUsageCount(Coupon $coupon, Order $order): int
    {
        $query = $coupon->usages();

        return $order->user_id !== null
            ? $query->where('user_id', $order->user_id)->count()
            : $query->where('guest_email', $order->guest_email)->count();
    }

    /**
     * Fixed and Percentage are both clamped to [0, subtotal] — the
     * Filament form already validates value into a sane range (0-100 for
     * Percentage, non-negative for Fixed), but this is the actual
     * enforcement boundary; a coupon's discount must never exceed what
     * the order is worth, or go negative and increase the total instead.
     */
    private function calculateDiscount(Coupon $coupon, Order $order): int
    {
        $discount = match ($coupon->type) {
            CouponType::Fixed => $coupon->value ?? 0,
            CouponType::Percentage => (int) round($order->subtotal * ($coupon->value ?? 0) / 100),
            CouponType::FreeShipping => 0,
        };

        return max(0, min($discount, $order->subtotal));
    }
}
