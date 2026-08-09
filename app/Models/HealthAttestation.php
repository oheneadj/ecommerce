<?php

/**
 * A human-recorded confirmation of something code cannot verify itself.
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HealthAttestationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Every confirmation is a new row, never an update-in-place — the point of
 * an attestation is an audit trail of who confirmed what and when, not
 * just the current state (docs/TASK-system-health-checks.md Step 5.1).
 * "Pass"/"fail" is never invented by code here — the dashboard only ever
 * shows "last confirmed by X on DATE" or "never confirmed."
 *
 * @property int $id
 * @property string $key
 * @property int $confirmed_by
 * @property Carbon $confirmed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'confirmed_by', 'confirmed_at', 'notes'])]
class HealthAttestation extends Model
{
    /** @use HasFactory<HealthAttestationFactory> */
    use HasFactory;

    /**
     * The required attestation keys, each with a staleness policy.
     * `stale_after_days` null means "per deployment — never expires once
     * done," matching the table in docs/TASK-system-health-checks.md §5.1.
     *
     * @var array<string, array{label: string, stale_after_days: int|null}>
     */
    public const REQUIRED = [
        'backup_restore_tested' => ['label' => 'Backup restore tested', 'stale_after_days' => 90],
        'real_payment_transaction_tested' => ['label' => 'Real payment transaction tested', 'stale_after_days' => null],
        'sms_verified_all_networks' => ['label' => 'SMS verified on all networks', 'stale_after_days' => null],
        'webhook_signature_verified' => ['label' => 'Webhook signature verified', 'stale_after_days' => null],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * The staff member who recorded this confirmation.
     *
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * The most recent confirmation for a given key, if any.
     */
    public static function latestFor(string $key): ?self
    {
        return self::query()->where('key', $key)->latest('confirmed_at')->first();
    }

    /**
     * Whether this confirmation is stale per its key's policy — always
     * false for a key with no expiry (`stale_after_days` null).
     */
    public function isStale(): bool
    {
        $staleAfterDays = self::REQUIRED[$this->key]['stale_after_days'] ?? null;

        if ($staleAfterDays === null) {
            return false;
        }

        return $this->confirmed_at->lt(now()->subDays($staleAfterDays));
    }
}
