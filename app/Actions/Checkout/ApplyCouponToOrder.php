<?php

/**
 * Validates and applies a coupon code to an order, recording its usage.
 */

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Checkout\Support\ValidateCoupon;
use App\Enums\CouponType;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One of three Actions in the whole system that requires row-level locking
 * (the others are ReserveStockForOrder and RecordStockMovement) — coupon
 * usage is a finite, contested resource exactly like stock. The Coupon row is locked, actual
 * `coupon_usages` rows are counted (never a cached counter column — a
 * counter can drift, rows cannot), and the usage limits are enforced
 * before the usage row is inserted, all inside the one locked transaction.
 * Validation rules themselves live in `ValidateCoupon`, shared with
 * `PreviewCouponDiscount` — this Action's own job is just locking +
 * persistence.
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

            if ($coupon === null) {
                throw new InvalidCouponException;
            }

            $items = $order->items()->with('productVariant.product')->get();

            ValidateCoupon::check($coupon, $order->subtotal, $items, $order->user_id, $order->guest_email);

            $discount = ValidateCoupon::discount($coupon, $order->subtotal);
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
}
