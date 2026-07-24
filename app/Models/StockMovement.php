<?php

/**
 * An immutable log entry of a single change in a variant's stock.
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Every stock change (sale, restock, adjustment, return, damage) is recorded
 * here rather than mutating `product_variants.stock` directly — the cached
 * total is derived from this history, and this table is the audit trail.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property string $type
 * @property int $quantity
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['product_variant_id', 'type', 'quantity', 'reference_type', 'reference_id', 'note', 'user_id'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
        ];
    }

    /**
     * The variant this movement affected.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * The staff member who performed this movement, if any (null when system-triggered).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record this movement is attributable to (e.g. an Order for a sale).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
