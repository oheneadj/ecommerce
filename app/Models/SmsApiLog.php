<?php

/**
 * An immutable record of a single outbound call to an SMS provider.
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SmsApiLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Mirrors PaymentApiLog's role for the SMS gateway — every call to Moolre
 * (OTP delivery or an ad-hoc staff-composed message) is recorded here in
 * full. An OTP send's message body embeds the plaintext code, so
 * request_payload/response_payload are encrypted at the column level
 * (CLAUDE.md §21: "if the payload contains sensitive fields, store them
 * encrypted ... rather than omitting them" — full traceability is
 * required, but exposure must still be controlled).
 *
 * @property int $id
 * @property string $provider
 * @property string $action
 * @property string $recipient
 * @property array<string, mixed> $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property int|null $status_code
 * @property Carbon|null $created_at
 */
#[Fillable(['provider', 'action', 'recipient', 'request_payload', 'response_payload', 'status_code'])]
class SmsApiLog extends Model
{
    /** @use HasFactory<SmsApiLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'encrypted:array',
            'response_payload' => 'encrypted:array',
        ];
    }
}
