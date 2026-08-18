<?php

/**
 * An admin-authored storefront announcement (sale notice, maintenance notice, etc.), shown as a banner or a popup.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use App\Enums\AnnouncementType;
use App\Enums\CustomerSegment;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Rows are never deleted once they've collected views — an expired one
 * stays as a queryable record of what was shown and its reach (see
 * `AnnouncementView`), the same "audit trail, not ephemeral state"
 * treatment `BackupRun` gets.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property AnnouncementType $type
 * @property CustomerSegment $audience
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int $priority
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'body', 'type', 'audience', 'starts_at', 'ends_at', 'priority', 'active'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, LogsAdminActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'audience' => CustomerSegment::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AnnouncementView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(AnnouncementView::class);
    }

    /**
     * Currently live by its own schedule — `active` flag on, and `now()`
     * falls inside [starts_at, ends_at] (an unset `ends_at` never expires
     * on its own; it stays live until deactivated by hand). Doesn't check
     * audience — that's a per-viewer decision, not something a single
     * query can express, see `CustomerSegment::matches()`.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeCurrentlyRunning(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * Used identically by both App\Livewire\Storefront\AnnouncementBanner
     * and AnnouncementPopup — each only ever resolves its own type, since
     * a banner and a popup can be running (and shown) at the same time,
     * independently of each other.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeOfType(Builder $query, AnnouncementType $type): Builder
    {
        return $query->where('type', $type);
    }
}
