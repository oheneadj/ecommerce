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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property VariantStatus $status
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
     * This variant's selected terms from the product's global attributes
     * (e.g. Size: Large, Color: Red) — the reusable-catalog counterpart to
     * attributeValues()'s free-typed custom values.
     *
     * @return BelongsToMany<AttributeTerm, $this>
     */
    public function attributeTerms(): BelongsToMany
    {
        return $this->belongsToMany(AttributeTerm::class, 'product_variant_attribute_term');
    }

    /**
     * Images specific to this variant.
     *
     * Images uploaded directly to this variant, in the admin-configured
     * display order (`sort_order`) — see `Product::images()`'s docblock
     * for why the ordering lives here rather than at each call site.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * The images to show for this variant, in priority order:
     *
     *   1. Images uploaded directly to this variant (`images()`) — an
     *      explicit override, e.g. a specific size photographed on its
     *      own.
     *   2. Images scoped to one of this variant's attribute terms (e.g.
     *      "Color: Green") — uploaded once on the product and shared by
     *      every variant carrying that term, so an admin doesn't have to
     *      re-upload the same photos for every size of a color.
     *   3. The product's general images (`product->images()` with no
     *      variant/term scope) — the final fallback.
     *
     * When a variant's terms match more than one term-scoped image set
     * (rare — e.g. images were mistakenly attached to a Size term too),
     * ties are broken by the product's attribute order, so the result is
     * deterministic rather than dependent on collection iteration order.
     *
     * Requires `product.images` and `attributeTerms` to already be
     * loaded (as the storefront product detail page does) — falls back
     * to querying otherwise.
     *
     * @return Collection<int, ProductImage>
     */
    public function galleryImages(): Collection
    {
        if ($this->images->isNotEmpty()) {
            return $this->images->values();
        }

        $productImages = $this->product->images;
        $variantTermIds = $this->attributeTerms->pluck('id')->all();

        // Deliberately not `$this->product->attributes` — accessed from
        // inside another Eloquent model's method, that property access
        // resolves to Model's own protected `$attributes` array (both
        // classes share Model as a common ancestor, so PHP allows the
        // protected access directly and never reaches the magic
        // __get()/relation resolution that a call from outside a Model
        // subclass would go through). `getRelation()`/the relation method
        // itself sidestep the collision entirely.
        $productAttributes = $this->product->relationLoaded('attributes')
            ? $this->product->getRelation('attributes')
            : $this->product->attributes()->with('terms')->get();

        foreach ($productAttributes as $attribute) {
            $attributeTermIds = $attribute->terms->pluck('id')->all();
            $matchingTermId = collect($variantTermIds)->first(fn ($id) => in_array($id, $attributeTermIds, true));

            if ($matchingTermId === null) {
                continue;
            }

            $termImages = $productImages->where('attribute_term_id', $matchingTermId);

            if ($termImages->isNotEmpty()) {
                return $termImages->values();
            }
        }

        return $productImages
            ->whereNull('attribute_term_id')
            ->whereNull('product_variant_id')
            ->values();
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
     * A human label distinguishing this variant from its siblings, for UI
     * that needs to list variants directly rather than through a global
     * Attribute selector (e.g. a product with no Attributes attached, only
     * per-variant SKUs/prices). Prefers global attribute terms, falls back
     * to custom per-variant attribute values, then the SKU — always
     * something, since every variant has a SKU.
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->relationLoaded('attributeTerms') && $this->attributeTerms->isNotEmpty()) {
            return $this->attributeTerms->pluck('value')->implode(' / ');
        }

        if ($this->relationLoaded('attributeValues') && $this->attributeValues->isNotEmpty()) {
            return $this->attributeValues->pluck('value')->implode(' / ');
        }

        return $this->sku;
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

    /**
     * Query-level equivalent of isLowStock(), so low-stock variants can be
     * filtered/counted directly in SQL instead of loading every row into
     * PHP just to check each one.
     *
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        $storeDefault = StoreSetting::current()->low_stock_threshold;

        return $query->where(function (Builder $query) use ($storeDefault): void {
            $query->where(function (Builder $query): void {
                $query->whereNotNull('low_stock_threshold')
                    ->whereColumn('stock', '<=', 'low_stock_threshold');
            })->orWhere(function (Builder $query) use ($storeDefault): void {
                $query->whereNull('low_stock_threshold')
                    ->where('stock', '<=', $storeDefault);
            });
        });
    }
}
