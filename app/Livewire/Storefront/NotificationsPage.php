<?php

/**
 * Customer-facing full notification history — every in-app notification this account has ever received.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Livewire\Storefront\Concerns\LinksToRelatedOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Paginated (unlike OrderHistoryPage's plain ->get()) — a notification
 * history has no natural cap the way a customer's own order count does,
 * so it can keep growing indefinitely.
 *
 * @property-read LengthAwarePaginator<int, DatabaseNotification> $notifications
 */
#[Title('Notifications')]
#[Lazy]
class NotificationsPage extends Component
{
    use LinksToRelatedOrder;
    use WithPagination;

    /**
     * The single notification currently expanded to show its full message
     * — only reached by non-order notifications, since an order
     * notification just navigates away on click instead.
     */
    public ?string $expandedNotificationId = null;

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return Auth::user()->notifications()->latest()->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.storefront.notifications-page');
    }

    /**
     * The one click-to-read action for a notification row: marks it read,
     * then either sends the customer to the order it's about, or (for
     * anything with nowhere to navigate, e.g. a staff broadcast) expands
     * the row in place to reveal its full message. Nothing is marked read
     * just by viewing this page — only by actually clicking a row.
     */
    public function openNotification(string $notificationId): void
    {
        $notification = collect($this->notifications->items())->firstWhere('id', $notificationId);

        if (! $notification) {
            return;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
            unset($this->notifications);
        }

        $orderUrl = $this->relatedOrderUrl($notification);

        if ($orderUrl !== null) {
            $this->redirect($orderUrl, navigate: true);

            return;
        }

        $this->expandedNotificationId = $this->expandedNotificationId === $notificationId ? null : $notificationId;
    }

    /**
     * Shown while the real component is still loading (see #[Lazy] above)
     * — the mark-as-read side effect in mount() above only runs once the
     * real component actually hydrates, same as everything else here.
     */
    public function placeholder(): View
    {
        return view('livewire.storefront.notifications-page-placeholder');
    }
}
