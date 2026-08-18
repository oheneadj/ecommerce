<?php

/**
 * Resolves which Announcement, if any, a specific visitor should currently see.
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Models\User;

/**
 * Shared by App\Livewire\Storefront\AnnouncementBanner and
 * AnnouncementPopup — same matching rules (schedule, active flag,
 * audience), differing only in `$type` and whether a prior dismissal
 * excludes a candidate (only ever true for popups; a banner has no
 * dismissal to check). Not an Action (per CLAUDE.md §9) since it's
 * reused by two genuinely separate entry points rather than one — a
 * Service is the right home for that.
 */
class AnnouncementMatcher
{
    /**
     * The single highest-priority currently-running announcement of the
     * given type that matches this visitor, or null. Records a view for
     * whatever it returns, as a side effect — `AnnouncementView`'s
     * unique(announcement_id, viewer_key) constraint makes that upsert
     * idempotent on a repeat visit either way.
     */
    public function matchFor(AnnouncementType $type, ?User $user, string $viewerKey, bool $excludeDismissed = false): ?Announcement
    {
        $candidates = Announcement::query()
            ->ofType($type)
            ->currentlyRunning()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $dismissedIds = $excludeDismissed
            ? AnnouncementView::query()
                ->where('viewer_key', $viewerKey)
                ->whereIn('announcement_id', $candidates->pluck('id'))
                ->whereNotNull('dismissed_at')
                ->pluck('announcement_id')
            : collect();

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
     * (announcement_id, viewer_key) means a repeat visit must never
     * throw, and must never touch the original viewed_at.
     */
    private function recordView(Announcement $announcement, string $viewerKey): void
    {
        AnnouncementView::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'viewer_key' => $viewerKey],
            ['viewed_at' => now()],
        );
    }
}
