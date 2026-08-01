<?php

/**
 * Attaches a past guest order to the customer's now-authenticated account.
 */

declare(strict_types=1);

namespace App\Actions\Order;

use App\Exceptions\GuestOrderClaimException;
use App\Models\Order;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Requires an already-authenticated session — this is never invoked
 * automatically from a matching email during checkout or login (BRD
 * FR-3.2a). A guest order's `user_id` is never set by any other code path;
 * this Action is the only way one gets attached to an account after the
 * fact, and only when the customer explicitly requests it while signed in.
 *
 * @throws GuestOrderClaimException when the order is already attached to
 *                                  an account, or the order's guest_email doesn't match the
 *                                  authenticated user's own (verified) email
 */
class ClaimGuestOrder
{
    use AsAction;

    public function handle(Order $order, User $user): Order
    {
        if ($order->user_id !== null) {
            throw new GuestOrderClaimException('This order already belongs to an account.');
        }

        if ($user->email === null || $user->email_verified_at === null || $order->guest_email === null || mb_strtolower($order->guest_email) !== mb_strtolower($user->email)) {
            throw new GuestOrderClaimException('This order does not match your account email.');
        }

        $order->update(['user_id' => $user->id]);

        return $order;
    }
}
