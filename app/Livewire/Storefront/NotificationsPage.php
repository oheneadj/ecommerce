<?php

/**
 * Customer-facing full notification history — every in-app notification this account has ever received.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

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
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return Auth::user()->notifications()->latest()->paginate(20);
    }

    /**
     * Viewing this page is the customer seeing every notification on it —
     * marks the whole current page as read, not the account's entire
     * history, so paging further still shows genuinely-unread items as
     * unread until that page is actually viewed.
     */
    public function mount(): void
    {
        foreach ($this->notifications->items() as $notification) {
            if ($notification->read_at === null) {
                $notification->markAsRead();
            }
        }
    }

    public function render(): View
    {
        return view('livewire.storefront.notifications-page');
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
