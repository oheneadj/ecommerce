<?php

/**
 * A single recorded use of a coupon against an order.
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CouponUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The only source of truth for `usage_limit`/`usage_limit_per_user` — these
 * rows are counted directly, never cached in a counter column (a cached
 * counter can drift; rows cannot). `guest_email` enforces the per-user
 * limit for guest orders, where there is no `user_id`.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $order_id
 * @property int|null $user_id
 * @property string|null $guest_email
 * @property Carbon|null $created_at
 */
#[Fillable(['coupon_id', 'order_id', 'user_id', 'guest_email'])]
class CouponUsage extends Model
{
    /** @use HasFactory<CouponUsageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The coupon this usage counts against.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The order this usage was recorded for.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The account that used the coupon, if any (null for a guest order).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
