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
 * existing account on email match (unlike an unverified match). If no
 * account matches, a new one is created with `google_id` and
 * `email_verified_at` trusted directly from Google's response.
 */
class LoginWithGoogle
{
    use AsAction;

    public function handle(SocialiteUser $googleUser): User
    {
        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();
        }

        if ($user) {
            if ($user->google_id === null) {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } else {
            $user = User::query()->create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        return $user;
    }
}
