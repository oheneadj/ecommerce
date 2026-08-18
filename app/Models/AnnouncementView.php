<?php

/**
 * One row per (announcement, viewer) — records that a specific visitor saw an announcement.
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
 * already uses for guest carts) — one shared shape, so reach counts work
 * identically for both without a nullable user_id column. A row is a
 * reach/impression record only — there's no dismissal to track;
 * `App\Livewire\Storefront\AnnouncementBanner` keeps showing an
 * announcement to everyone it targets for as long as its own
 * schedule/active flag says it should, by design.
 *
 * @property int $id
 * @property int $announcement_id
 * @property string $viewer_key
 * @property Carbon $viewed_at
 */
#[Fillable(['announcement_id', 'viewer_key', 'viewed_at'])]
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
