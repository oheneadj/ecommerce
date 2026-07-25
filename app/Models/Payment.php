<?php

/**
 * A single payment attempt against an order.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasFormattedMoney;
use App\Concerns\HasUlid;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Created by InitiatePayment (status: pending), transitioned by
 * HandlePaymentWebhook or the VerifyPendingPayments polling fallback —
 * never mutated directly from a controller. Card/payment details are never
 * handled by this platform; `metadata` holds the provider's own callback
 * payload, never a raw card number.
 *
 * @property int $id
 * @property string $ulid
 * @property int $order_id
 * @property string $provider
 * @property string|null $provider_reference
 * @property string|null $channel
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_id', 'provider', 'provider_reference', 'channel', 'amount', 'currency', 'status', 'metadata'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasFormattedMoney, HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'metadata' => 'array',
        ];
    }

    /**
     * The order this payment is for.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Refunds issued against this payment.
     *
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * This payment's amount formatted for display (e.g. "GH₵150.00").
     */
    public function getAmountFormattedAttribute(): string
    {
        return $this->formattedMoney($this->amount, $this->currency);
    }
}
