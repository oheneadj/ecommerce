<?php

/**
 * The site-wide storefront popup for admin-authored announcements — the dismissible counterpart to AnnouncementBanner.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Services\AnnouncementMatcher;
use App\Support\AnnouncementViewerKey;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Shows at most one popup-type announcement at a time, same
 * priority/audience matching AnnouncementBanner uses (see
 * App\Services\AnnouncementMatcher) — but unlike a banner, a popup can be
 * dismissed, and dismissal is permanent: once closed, it never shows to
 * that same visitor again. A banner can be showing at the same time,
 * independently.
 *
 * @property-read Announcement|null $announcement
 */
class AnnouncementPopup extends Component
{
    #[Computed]
    public function announcement(): ?Announcement
    {
        return app(AnnouncementMatcher::class)->matchFor(
            AnnouncementType::Popup,
            Auth::user(),
            AnnouncementViewerKey::current(),
            excludeDismissed: true,
        );
    }

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
        return view('livewire.storefront.announcement-popup');
    }
}
