<?php

/**
 * One row per (announcement, viewer) — records that a specific visitor saw an announcement, and, for a popup, whether they dismissed it.
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AnnouncementViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * `viewer_key` covers both logged-in customers ("user_{id}") and guests
 * (the same session-id convention `ResolveCurrentCart::guestSessionId()`
 * already uses for guest carts) — one shared shape, so reach/dismiss
 * numbers work identically for both without a nullable user_id column.
 * `dismissed_at` only ever gets set for a `popup`-type announcement
 * (`App\Livewire\Storefront\AnnouncementPopup`) and is permanent once
 * set — a `banner` stays visible to everyone it targets for as long as
 * its own schedule/active flag says it should; only a popup can be
 * dismissed.
 *
 * @property int $id
 * @property int $announcement_id
 * @property string $viewer_key
 * @property Carbon $viewed_at
 * @property Carbon|null $dismissed_at
 */
#[Fillable(['announcement_id', 'viewer_key', 'viewed_at', 'dismissed_at'])]
class AnnouncementView extends Model
{
    /** @use HasFactory<AnnouncementViewFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
