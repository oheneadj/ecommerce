<?php

/**
 * HTTP entry points for customer Google OAuth login.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LinkAccountIdentifier;
use App\Actions\Auth\LoginWithGoogle;
use App\Exceptions\AccountDisabledException;
use App\Exceptions\AccountIdentifierAlreadyLinkedException;
use App\Exceptions\GoogleEmailAlreadyTakenException;
use App\Exceptions\GoogleEmailConflictException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Thin redirect/callback pair — all account creation/linking logic lives in
 * LoginWithGoogle (guest login/registration) and LinkAccountIdentifier
 * (adding Google to an already-authenticated account).
 */
class GoogleAuthController extends Controller
{
    /**
     * Redirect the customer to Google's OAuth consent screen. `redirect_to`
     * is only meaningful for the already-authenticated "connect Google"
     * flow (Profile/Security) — stashed in the session since Google's own
     * callback URL is fixed and can't carry it through the round trip.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        $redirectTo = request()->string('redirect_to')->toString();

        // Only a same-app relative path is ever accepted — an absolute or
        // protocol-relative URL here would let an attacker send a logged-in
        // customer through a real Google consent screen and land them on an
        // external phishing page immediately after a trusted "connected"
        // message.
        if (Auth::check() && $redirectTo !== '' && str_starts_with($redirectTo, '/') && ! str_starts_with($redirectTo, '//')) {
            session(['google_link_redirect_to' => $redirectTo]);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google's OAuth callback: log the guest in via LoginWithGoogle,
     * or link the identifier to the current session if already authenticated.
     */
    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        if (Auth::check()) {
            $redirectTo = session()->pull('google_link_redirect_to', route('account.show', absolute: false));

            try {
                LinkAccountIdentifier::run(Auth::user(), $googleUser->getId(), $googleUser->getEmail());
            } catch (AccountIdentifierAlreadyLinkedException|GoogleEmailAlreadyTakenException $e) {
                return redirect()->to($redirectTo)->with('error', $e->getMessage());
            }

            return redirect()->to($redirectTo)->with('status', __('Google account connected.'));
        }

        try {
            LoginWithGoogle::run($googleUser);
        } catch (GoogleEmailConflictException|AccountDisabledException $e) {
            return redirect()->route('login.phone')->with('error', $e->getMessage());
        }

        return redirect()->intended(route('account.show', absolute: false));
    }
}
