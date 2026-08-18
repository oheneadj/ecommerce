<?php

/**
 * A persistent reminder banner shown across every account/settings page for an account with an unverified email.
 */

declare(strict_types=1);

namespace App\Livewire\Account;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Embedded once in `x-account.layout` so it shows consistently across every
 * account-area page (dashboard, orders, addresses, notifications, wishlist,
 * profile, security) rather than being scattered as one-off conditionals —
 * a customer sees the same reminder no matter which page they land on.
 */
class VerifyEmailBanner extends Component
{
    #[Computed]
    public function shouldShow(): bool
    {
        return Auth::user()->hasUnverifiedEmailAddress();
    }

    /**
     * Resend the verification email. Rate-limited (unlike the OTP/SMS
     * flows, this isn't billed per-send, but it's still an authenticated
     * self-service action a customer could otherwise mash repeatedly).
     */
    public function resend(): void
    {
        $user = Auth::user();

        if (! $user->hasUnverifiedEmailAddress()) {
            return;
        }

        $key = "verify-email-resend:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->dispatch('toast', message: __('A verification email was already sent recently — check your inbox.'));

            return;
        }

        RateLimiter::hit($key, 60);

        $user->sendEmailVerificationNotification();

        $this->dispatch('toast', variant: 'success', message: __('A new verification link has been sent to your email address.'));
    }

    public function render(): View
    {
        return view('livewire.account.verify-email-banner');
    }
}
