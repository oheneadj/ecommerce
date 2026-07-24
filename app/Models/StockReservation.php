<?php

/**
 * A temporary hold on a variant's stock while a customer completes checkout.
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockReservationStatus;
use Database\Factories\StockReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Created by ReserveStockForOrder, released by the scheduled
 * ReleaseExpiredReservations job if payment never completes, or converted
 * into a `sale` StockMovement by HandlePaymentWebhook on success.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int|null $order_id
 * @property int $quantity
 * @property string $status
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_variant_id', 'order_id', 'quantity', 'status', 'expires_at'])]
class StockReservation extends Model
{
    /** @use HasFactory<StockReservationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockReservationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The variant this reservation holds stock against.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
