<?php

/**
 * A refund issued against a payment, in full or in part.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasFormattedMoney;
use App\Concerns\HasUlid;
use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * `amount` never exceeds its parent Payment's amount — enforced only by
 * ProcessRefund (cross-row arithmetic isn't a DB constraint, per
 * technical-design-ecommerce.md §4g's Consistency table).
 *
 * @property int $id
 * @property string $ulid
 * @property int $payment_id
 * @property int $amount
 * @property string $status
 * @property string|null $provider_refund_reference
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['payment_id', 'amount', 'status', 'provider_refund_reference', 'reason'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory, HasFormattedMoney, HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
        ];
    }

    /**
     * The payment this refund was issued against.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * This refund's amount formatted for display (e.g. "GH₵150.00").
     */
    public function getAmountFormattedAttribute(): string
    {
        return $this->formattedMoney($this->amount);
    }
}
