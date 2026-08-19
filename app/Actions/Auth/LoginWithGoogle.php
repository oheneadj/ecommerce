<?php

/**
 * Logs in (or creates) a customer account from a Google OAuth callback.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\AccountDisabledException;
use App\Exceptions\GoogleEmailConflictException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A first-time Google sign-in only auto-links onto an existing account when
 * BOTH sides of the email match are independently verified: Google's own
 * `email_verified` claim, and the existing account's own
 * `email_verified_at` (set by clicking a real verification link, or by an
 * earlier Google login). Checking only Google's side — as this used to —
 * let anyone who owns a real Google account silently take over a different
 * customer's account, just by that account having self-typed the same
 * email into Profile settings without ever verifying it. Two people's
 * unverified claims about the same address must never be trusted to mean
 * "same person" on their own (CLAUDE.md's identifier-linking rule);
 * Google's OAuth handshake only proves the *Google* side.
 *
 * When the existing account's email isn't verified, the login is refused
 * (GoogleEmailConflictException) rather than silently creating a second,
 * permanently-separate account — a customer stuck this way still has two
 * working paths out: click the verification email this also sends, then
 * retry Google (both sides now verified); or log in with their original
 * method and connect Google from Security settings (LinkAccountIdentifier,
 * which doesn't need email verification at all since the customer is
 * already proven to be themselves by being authenticated).
 */
class LoginWithGoogle
{
    use AsAction;

    /**
     * @throws GoogleEmailConflictException when a first-time sign-in matches an account whose email isn't independently verified
     * @throws AccountDisabledException when the resolved account has been disabled
     */
    public function handle(SocialiteUser $googleUser): User
    {
        $emailVerifiedByGoogle = ($googleUser->user['email_verified'] ?? true) !== false;

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if ($user) {
            if ($user->disabled_at !== null) {
                throw new AccountDisabledException;
            }

            // A normal repeat login, not a linking decision — this Google
            // account is already this account's own. Still worth topping
            // up email_verified_at if Google now confirms an email this
            // account's own verification link was never clicked for.
            if ($user->email_verified_at === null && $emailVerifiedByGoogle) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            Auth::login($user, remember: true);

            return $user;
        }

        $matchingEmailUser = ($emailVerifiedByGoogle && $googleUser->getEmail() !== null)
            ? User::query()->where('email', $googleUser->getEmail())->first()
            : null;

        if ($matchingEmailUser) {
            if (! $matchingEmailUser->hasVerifiedEmailAddress()) {
                $this->sendConflictVerificationEmail($matchingEmailUser);

                throw new GoogleEmailConflictException;
            }

            if ($matchingEmailUser->disabled_at !== null) {
                throw new AccountDisabledException;
            }

            $matchingEmailUser->forceFill(['google_id' => $googleUser->getId()])->save();
            Auth::login($matchingEmailUser, remember: true);

            return $matchingEmailUser;
        }

        // No match at all — a genuinely new customer. An unverified claim
        // can't be trusted to link, but the email column is still globally
        // unique — if it's already claimed by another account, this new
        // (unlinked) account is created without it rather than crashing on
        // the constraint.
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

        Auth::login($user, remember: true);

        return $user;
    }

    /**
     * Best-effort, rate-limited (1 per 5 minutes per account) — this fires
     * from an unauthenticated context, so without a limit it'd be a free
     * way to spam an arbitrary email's inbox by repeatedly attempting
     * Google login against it.
     */
    private function sendConflictVerificationEmail(User $user): void
    {
        $key = "google-email-conflict-verify:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return;
        }

        RateLimiter::hit($key, 300);

        $user->sendEmailVerificationNotification();
    }
}
