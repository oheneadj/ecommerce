<?php

/**
 * A single permanently-priced line item within an Order.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasFormattedMoney;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * `item_snapshot` and `unit_price` are captured once, permanently, at Order
 * creation time — never re-read from the live Product/ProductVariant tables
 * afterward, even if the product is later edited, archived, or deleted
 * (BRD Principle 8). `productVariant()` exists only as a convenience link
 * (e.g. for internal stock reconciliation), never for rendering the order.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_variant_id
 * @property array<string, mixed> $item_snapshot
 * @property int $unit_price
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_id', 'product_variant_id', 'item_snapshot', 'unit_price', 'quantity'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory, HasFormattedMoney;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_snapshot' => 'array',
        ];
    }

    /**
     * The order this line item belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Convenience link to the variant — never read from for display.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * This line item's unit price formatted for display (e.g. "GH₵15.50").
     */
    public function getUnitPriceFormattedAttribute(): string
    {
        return $this->formattedMoney($this->unit_price);
    }
}
