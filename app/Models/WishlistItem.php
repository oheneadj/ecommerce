<?php

/**
 * A variant a registered customer has saved for later.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUlid;
use Database\Factories\WishlistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registered customers only — no guest wishlist (BRD FR-8.1). Saved at
 * variant level, not product level, consistent with cart/order lines
 * always referencing a specific variant. `(user_id, product_variant_id)`
 * is unique — adding an already-wishlisted variant again is a no-op, not
 * a duplicate row.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int $product_variant_id
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'product_variant_id'])]
class WishlistItem extends Model
{
    /** @use HasFactory<WishlistItemFactory> */
    use HasFactory, HasUlid;

    public const UPDATED_AT = null;

    /**
     * The account this wishlist item belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The wishlisted variant.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
