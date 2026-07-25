<?php

/**
 * An immutable record of a single outbound call to a payment provider.
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PaymentApiLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit-exempt from business transactions (technical-design-ecommerce.md
 * §4g): written and committed on its own, before any related business
 * processing — a rollback of order/payment bookkeeping must never erase
 * the evidence that a call to Moolre/Paystack genuinely happened. Never
 * contains a raw card number — the platform never handles card data
 * directly.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $payment_id
 * @property string $provider
 * @property string $action
 * @property array<string, mixed> $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property int|null $status_code
 * @property Carbon|null $created_at
 */
#[Fillable(['order_id', 'payment_id', 'provider', 'action', 'request_payload', 'response_payload', 'status_code'])]
class PaymentApiLog extends Model
{
    /** @use HasFactory<PaymentApiLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    /**
     * The order this call relates to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The payment this call relates to, if one exists yet.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
