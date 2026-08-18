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
 * segment. Deliberately not dismissible — an announcement stays visible
 * to everyone it targets for as long as its own schedule/active flag says
 * it should; a visitor can't opt out of seeing it, only an admin turning
 * it off or letting it expire ends it.
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
        $user = Auth::user();

        $match = Announcement::query()
            ->currentlyRunning()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Announcement $announcement): bool => $announcement->audience->matches($user));

        if ($match !== null) {
            $this->recordView($match);
        }

        return $match;
    }

    /**
     * Upserts rather than a plain create — the unique constraint on
     * (announcement_id, viewer_key) means a repeat visit must never
     * throw, and must never touch the original viewed_at.
     */
    private function recordView(Announcement $announcement): void
    {
        AnnouncementView::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'viewer_key' => AnnouncementViewerKey::current()],
            ['viewed_at' => now()],
        );
    }

    public function render(): View
    {
        return view('livewire.storefront.announcement-banner');
    }
}
