<?php

/**
 * Sends the account's first verification email right after email+password registration.
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Laravel's own built-in `SendEmailVerificationNotification` listener skips
 * sending because it checks `$event->user instanceof
 * Illuminate\Contracts\Auth\MustVerifyEmail` — an interface `User`
 * deliberately doesn't implement (see User::class docblock), so this fires
 * unconditionally instead. `Registered` only ever dispatches from Fortify's
 * email+password registration — phone/Google logins authenticate via
 * `Auth::login()` directly, never this event.
 */
class SendEmailVerificationOnRegistration
{
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->sendEmailVerificationNotification();
    }
}
