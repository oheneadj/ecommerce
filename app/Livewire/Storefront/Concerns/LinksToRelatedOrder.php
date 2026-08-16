<?php

/**
 * Resolves the order-detail URL a notification should navigate to, if any.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront\Concerns;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by NotificationsPage and NotificationIndicator so both the full
 * history and the bell-dropdown preview resolve order links the same way.
 * Only order-related notifications (OrderPlaced, OrderShipped,
 * PaymentSucceeded, PaymentFailed) carry an `order_id` in their stored
 * data — anything else (e.g. a staff broadcast) has nowhere to navigate.
 */
trait LinksToRelatedOrder
{
    public function relatedOrderUrl(DatabaseNotification $notification): ?string
    {
        $orderId = $notification->data['order_id'] ?? null;

        if ($orderId === null) {
            return null;
        }

        $order = Auth::user()?->orders()->find($orderId);

        return $order ? route('account.orders.show', $order) : null;
    }
}
