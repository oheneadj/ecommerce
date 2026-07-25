<?php

/**
 * An immutable record of a single inbound webhook notification.
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Audit-exempt from business transactions, same rule as PaymentApiLog:
 * written and committed before any business processing runs, so a rollback
 * of order/payment bookkeeping never erases evidence that a webhook
 * genuinely arrived. `(provider, event_id)` is uniquely constrained — the
 * first defense against a duplicate delivery double-processing a payment;
 * `processed_at` is the second (checked in HandlePaymentWebhook before any
 * side effect runs).
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property array<string, mixed> $payload
 * @property bool $verified
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 */
#[Fillable(['provider', 'event_id', 'payload', 'verified', 'processed_at'])]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'verified' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
