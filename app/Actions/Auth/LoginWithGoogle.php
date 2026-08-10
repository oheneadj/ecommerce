<?php

/**
 * Logs in (or creates) a customer account from a Google OAuth callback.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Google always returns a verified email, so it's safe to auto-link to an
 * existing account on email match (unlike an unverified match) — but only
 * when Google's own `email_verified` claim actually confirms it (virtually
 * always true for standard OAuth, but checked explicitly rather than
 * assumed, since trusting an unverified claim here would open the same
 * account-linking risk this rule exists to prevent). If no account
 * matches, a new one is created with `google_id`, and `email_verified_at`
 * is only trusted from Google's response when that claim holds.
 */
class LoginWithGoogle
{
    use AsAction;

    public function handle(SocialiteUser $googleUser): User
    {
        $emailVerifiedByGoogle = ($googleUser->user['email_verified'] ?? true) !== false;

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user && $emailVerifiedByGoogle) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();
        }

        if ($user) {
            // One save, not two sequential ones — both fields belong to
            // the same row, so there's no reason to risk a partial update
            // (google_id set, email_verified_at not) if something failed
            // between separate calls.
            $changes = [];

            if ($user->google_id === null) {
                $changes['google_id'] = $googleUser->getId();
            }

            if ($user->email_verified_at === null && $emailVerifiedByGoogle) {
                $changes['email_verified_at'] = now();
            }

            if ($changes !== []) {
                $user->forceFill($changes)->save();
            }
        } else {
            // An unverified claim can't be trusted to auto-link, but the
            // email column is still globally unique — if it's already
            // claimed by another account, this new (unlinked) account is
            // created without it rather than crashing on the constraint.
            $emailAlreadyTaken = $googleUser->getEmail() !== null
                && User::query()->where('email', $googleUser->getEmail())->exists();

            $user = User::query()->create([
                'name' => $googleUser->getName(),
                'email' => ($emailVerifiedByGoogle || ! $emailAlreadyTaken) ? $googleUser->getEmail() : null,
                'google_id' => $googleUser->getId(),
            ]);

            if ($emailVerifiedByGoogle) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        Auth::login($user, remember: true);

        return $user;
    }
}
