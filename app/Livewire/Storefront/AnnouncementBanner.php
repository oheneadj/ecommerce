<?php

/**
 * The site-wide storefront banner for admin-authored announcements (sales, maintenance notices, etc.).
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Support\AnnouncementViewerKey;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Shows at most one announcement at a time — the highest-priority
 * currently-running one that matches this specific visitor's audience
 * segment and that they haven't already dismissed. Dismissal is
 * permanent (see AnnouncementView's own docblock for why) — no snooze,
 * no re-show.
 *
 * @property-read Announcement|null $announcement
 */
class AnnouncementBanner extends Component
{
    /**
     * The one announcement to actually show, or null once nothing
     * currently-running matches this viewer (including "nothing exists
     * at all," the common case). Recording the view (see recordView())
     * happens as a side effect of resolving this, not on every render —
     * `AnnouncementView`'s unique(announcement_id, viewer_key) constraint
     * makes the upsert idempotent either way.
     */
    #[Computed]
    public function announcement(): ?Announcement
    {
        $viewerKey = AnnouncementViewerKey::current();
        $user = Auth::user();

        $candidates = Announcement::query()
            ->currentlyRunning()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $dismissedIds = AnnouncementView::query()
            ->where('viewer_key', $viewerKey)
            ->whereIn('announcement_id', $candidates->pluck('id'))
            ->whereNotNull('dismissed_at')
            ->pluck('announcement_id');

        $match = $candidates
            ->reject(fn (Announcement $announcement): bool => $dismissedIds->contains($announcement->id))
            ->first(fn (Announcement $announcement): bool => $announcement->audience->matches($user));

        if ($match !== null) {
            $this->recordView($match, $viewerKey);
        }

        return $match;
    }

    /**
     * Upserts rather than a plain create — the unique constraint on
     * (announcement_id, viewer_key) means a repeat visit before dismissal
     * must never throw, and must never touch the original viewed_at.
     */
    private function recordView(Announcement $announcement, string $viewerKey): void
    {
        AnnouncementView::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'viewer_key' => $viewerKey],
            ['viewed_at' => now()],
        );
    }

    /**
     * Permanent — see AnnouncementView's own docblock for why this
     * announcement never comes back for this viewer after this.
     */
    public function dismiss(): void
    {
        $announcement = $this->announcement;

        if ($announcement === null) {
            return;
        }

        AnnouncementView::query()
            ->where('announcement_id', $announcement->id)
            ->where('viewer_key', AnnouncementViewerKey::current())
            ->update(['dismissed_at' => now()]);

        unset($this->announcement);
    }

    public function render(): View
    {
        return view('livewire.storefront.announcement-banner');
    }
}
