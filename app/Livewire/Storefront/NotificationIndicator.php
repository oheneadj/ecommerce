<?php

/**
 * The header bell icon — unread count badge plus a preview of the customer's most recent in-app notifications.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Livewire\Storefront\Concerns\LinksToRelatedOrder;
use App\Notifications\Support\CustomerFacingNotifications;
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
    use LinksToRelatedOrder;

    public bool $open = false;

    /**
     * The single preview item currently expanded to show its full message
     * — only reached by non-order notifications, since an order
     * notification just navigates away on click instead.
     */
    public ?string $expandedNotificationId = null;

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()?->unreadNotifications()
            ->whereIn('type', CustomerFacingNotifications::types())
            ->count() ?? 0;
    }

    /**
     * The top unread notifications — read ones don't need a spot in a
     * badge-driven preview whose whole purpose is surfacing what's new.
     *
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function recent(): Collection
    {
        return Auth::user()?->unreadNotifications()
            ->whereIn('type', CustomerFacingNotifications::types())
            ->latest()
            ->limit(5)
            ->get() ?? new Collection;
    }

    /**
     * Just opens/closes the dropdown — nothing is marked read by viewing
     * it, only by actually clicking a notification (see openNotification).
     */
    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    /**
     * Clicking a preview item marks it read, then either navigates to the
     * order it's about, or (for anything with nowhere to navigate, e.g. a
     * staff broadcast) expands the row in place to reveal its full
     * message — matches the full notifications page's behavior. The
     * `recent` list itself is deliberately left uninvalidated here so an
     * expanded item doesn't vanish mid-interaction just because marking it
     * read would otherwise drop it out of the unread-only preview.
     */
    public function openNotification(string $notificationId): void
    {
        $notification = $this->recent->firstWhere('id', $notificationId);

        if (! $notification) {
            return;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
            unset($this->unreadCount);
        }

        $orderUrl = $this->relatedOrderUrl($notification);

        if ($orderUrl !== null) {
            $this->redirect($orderUrl, navigate: true);

            return;
        }

        $this->expandedNotificationId = $this->expandedNotificationId === $notificationId ? null : $notificationId;
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
