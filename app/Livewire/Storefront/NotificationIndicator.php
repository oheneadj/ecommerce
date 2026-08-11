<?php

/**
 * The header bell icon — unread count badge plus a preview of the customer's most recent in-app notifications.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Only rendered for logged-in customers (guarded in the layout) — an
 * anonymous visitor has no `notifications` rows to read. Same
 * `#[Lazy]`-with-placeholder, click-to-toggle-dropdown pattern as
 * `CartIndicator`, so the outer page shell paints immediately.
 *
 * @property-read int $unreadCount
 * @property-read Collection<int, DatabaseNotification> $recent
 */
#[Lazy]
class NotificationIndicator extends Component
{
    public bool $open = false;

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()?->unreadNotifications()->count() ?? 0;
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function recent(): Collection
    {
        return Auth::user()?->notifications()->latest()->limit(5)->get() ?? new Collection;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->markRecentAsRead();
        }
    }

    /**
     * Opening the dropdown is the customer actually seeing these — marks
     * just the batch currently shown as read, not the whole notification
     * history, so a customer with older unread notifications beyond the
     * preview list doesn't have them silently marked read without ever
     * being shown.
     */
    private function markRecentAsRead(): void
    {
        foreach ($this->recent as $notification) {
            if ($notification->read_at === null) {
                $notification->markAsRead();
            }
        }

        unset($this->unreadCount, $this->recent);
    }

    public function render(): View
    {
        return view('livewire.storefront.notification-indicator');
    }

    /**
     * Matches the real button's exact markup (icon + label, no badge) so
     * there's no layout shift when the follow-up request swaps it in.
     */
    public function placeholder(): View
    {
        return view('livewire.storefront.notification-indicator-placeholder');
    }
}
