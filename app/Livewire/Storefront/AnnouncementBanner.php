<?php

/**
 * The site-wide storefront banner for admin-authored announcements (sales, maintenance notices, etc.).
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Services\AnnouncementMatcher;
use App\Support\AnnouncementViewerKey;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Shows at most one banner-type announcement at a time — the
 * highest-priority currently-running one that matches this specific
 * visitor's audience segment. Deliberately not dismissible — a banner
 * stays visible to everyone it targets for as long as its own
 * schedule/active flag says it should; a visitor can't opt out of seeing
 * it, only an admin turning it off or letting it expire ends it. A
 * popup-type announcement never appears here — see AnnouncementPopup,
 * which can be showing at the same time, independently.
 *
 * @property-read Announcement|null $announcement
 */
class AnnouncementBanner extends Component
{
    #[Computed]
    public function announcement(): ?Announcement
    {
        return app(AnnouncementMatcher::class)->matchFor(
            AnnouncementType::Banner,
            Auth::user(),
            AnnouncementViewerKey::current(),
        );
    }

    public function render(): View
    {
        return view('livewire.storefront.announcement-banner');
    }
}
