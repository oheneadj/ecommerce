<?php

/**
 * Folds a guest's session cart into their account cart the moment they log in.
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Cart\MergeGuestCartIntoUser;
use App\Actions\Cart\ResolveCurrentCart;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Fires for every login path (phone OTP, Google, email+password, 2FA,
 * passkeys) — `Illuminate\Auth\SessionGuard::login()` always dispatches
 * `Login`, regardless of how authentication happened.
 */
class MergeGuestCartOnLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $guestCart = Cart::query()
            ->where('session_id', ResolveCurrentCart::guestSessionId())
            ->whereNull('user_id')
            ->whereDoesntHave('order')
            ->first();

        if ($guestCart !== null) {
            MergeGuestCartIntoUser::run($guestCart, $event->user);
        }
    }
}
