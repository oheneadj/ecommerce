<?php

/**
 * A discount code applicable to a whole cart or scoped to specific products/categories.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUlid;
use App\Enums\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Scope is defined by the `products`/`categories` pivots — empty on both
 * means the coupon applies cart-wide. `usage_limit`/`usage_limit_per_user`
 * are always enforced by counting actual `coupon_usages` rows, never a
 * cached counter (see ApplyCouponToOrder).
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property CouponType $type
 * @property int|null $value
 * @property int|null $min_order_amount
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property Carbon|null $expires_at
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'type', 'value', 'min_order_amount', 'usage_limit', 'usage_limit_per_user', 'expires_at', 'active'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * Products this coupon is scoped to (empty = cart-wide, unless categories also scope it).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    /**
     * Categories this coupon is scoped to (empty = cart-wide, unless products also scope it).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    /**
     * Every recorded use of this coupon — the source of truth for usage limits.
     *
     * @return HasMany<CouponUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Whether this coupon has any product/category scoping at all.
     */
    public function isScoped(): bool
    {
        return $this->products()->exists() || $this->categories()->exists();
    }
}
