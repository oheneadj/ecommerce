<?php

/**
 * Resolves the correct notification recipient for an order.
 */

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * A registered customer's own User model is Notifiable and gets the
 * "database" channel too (surfacing in their account and, for staff
 * notifications, the Filament bell). A guest order has no Notifiable
 * model at all, so it's routed to an on-demand AnonymousNotifiable built
 * from whichever of guest_email/guest_phone were actually captured —
 * whichever identifiers exist determine delivery, never both assumed
 * present (BRD: a Google-only or phone-only customer never gets a channel
 * they have no route for).
 */
class OrderRecipient
{
    /**
     * @return User|AnonymousNotifiable
     */
    public static function for(Order $order): mixed
    {
        if ($order->user_id !== null) {
            return $order->user;
        }

        $anonymous = new AnonymousNotifiable;

        if ($order->guest_email !== null) {
            $anonymous->route('mail', $order->guest_email);
        }

        if ($order->guest_phone !== null) {
            $anonymous->route('sms', $order->guest_phone);
        }

        return $anonymous;
    }
}
