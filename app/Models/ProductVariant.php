<?php

/**
 * The product variant model — the actual sellable unit.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasFormattedMoney;
use App\Concerns\HasUlid;
use App\Concerns\LogsAdminActivity;
use App\Enums\VariantStatus;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A specific sellable unit of a Product (e.g. "Large / Red"), with its own
 * price, SKU, and stock. Orders always reference a variant, never a bare
 * Product — this is the row every cart/order/stock-movement points to.
 *
 * @property int $id
 * @property string $ulid
 * @property int $product_id
 * @property string $sku
 * @property int $price
 * @property int $stock
 * @property int|null $low_stock_threshold
 * @property string $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_id', 'sku', 'price', 'stock', 'low_stock_threshold', 'status'])]
#[Hidden(['deleted_at'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, HasFormattedMoney, HasUlid, LogsAdminActivity, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VariantStatus::class,
        ];
    }

    /**
     * The product this variant belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * This variant's attribute values (e.g. Size: Large, Color: Red).
     *
     * @return HasMany<AttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

    /**
     * Images specific to this variant.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * This variant's stock movement history.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Stock reservations held against this variant.
     *
     * @return HasMany<StockReservation, $this>
     */
    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * The variant's price formatted for display (e.g. "GH₵15.50").
     */
    public function getPriceFormattedAttribute(): string
    {
        return $this->formattedMoney($this->price);
    }

    /**
     * This variant's own threshold, or the store-wide default if unset.
     */
    public function effectiveLowStockThreshold(): int
    {
        return $this->low_stock_threshold ?? StoreSetting::current()->low_stock_threshold;
    }

    /**
     * Whether this variant's cached stock is at or below its low-stock threshold.
     */
    public function isLowStock(): bool
    {
        return $this->stock <= $this->effectiveLowStockThreshold();
    }
}
