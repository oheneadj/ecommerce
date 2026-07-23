<?php

/**
 * The one-time-passcode model backing phone login.
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A hashed, single-use, short-lived code sent to a phone number to authenticate
 * a login. The plaintext code is never persisted — only `code_hash`.
 *
 * @property int $id
 * @property string $identifier
 * @property string $code_hash
 * @property string $purpose
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property int $attempts
 * @property Carbon|null $created_at
 */
#[Fillable(['identifier', 'code_hash', 'purpose', 'expires_at'])]
#[Hidden(['code_hash'])]
class OtpCode extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Whether this code can still be verified: not expired, not already consumed,
     * and not locked out after too many failed attempts.
     */
    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < 5;
    }
}
