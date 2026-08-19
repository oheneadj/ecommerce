<?php

/**
 * The allow-list of notification classes the customer-facing storefront is allowed to read.
 */

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Notifications\CustomerBroadcastNotification;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderShipped;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSucceeded;

/**
 * Laravel's `notifications` table is shared across every `User` row —
 * staff and customers alike — and Filament's own admin bell
 * (`->databaseNotifications()` in AdminPanelProvider) reads from that same
 * table with no filtering, by design: any authenticated admin should see
 * every staff-facing alert (BackupFailed, CriticalHealthAlert,
 * LowStockAlert, etc.) addressed to them.
 *
 * The storefront's notification bell/list never applied the equivalent
 * filter in the other direction. A Super Admin account is still a plain
 * `User` row, so logging into the customer-facing storefront with that
 * same account surfaced internal ops alerts (a backup failure, a health
 * check) in a UI built only to render order-related messages — confusing
 * at best, and a staff-only operational detail leaking into a customer
 * surface at worst.
 *
 * `NotificationsPage` and `NotificationIndicator` both filter their
 * queries to this list — the reverse of the admin bell needing no filter
 * at all.
 */
class CustomerFacingNotifications
{
    /**
     * @return array<int, class-string>
     */
    public static function types(): array
    {
        return [
            OrderPlaced::class,
            OrderShipped::class,
            PaymentSucceeded::class,
            PaymentFailed::class,
            CustomerBroadcastNotification::class,
        ];
    }
}
