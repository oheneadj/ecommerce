<?php

/**
 * A single variant/quantity line within a cart.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUlid;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Deliberately reads the variant's live price whenever the cart is
 * displayed — the cart never locks in a price (BRD Principle 8). Only
 * OrderItem, created at checkout, permanently snapshots price/details.
 *
 * @property int $id
 * @property string $ulid
 * @property int $cart_id
 * @property int $product_variant_id
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['cart_id', 'product_variant_id', 'quantity'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory, HasUlid;

    /**
     * The cart this item belongs to.
     *
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The variant this line item is for.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
