<?php

/**
 * Shared "Connect Google" / "Set password" state and actions for account-security-adjacent Livewire pages.
 */

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Used by both Profile and Security settings — each page shows the same
 * "add a login method" actions (BRD: email+password accounts can add
 * Google/phone; phone/Google accounts can add email+password), so the
 * state and persistence logic live here once rather than duplicated
 * across both components.
 */
trait ManagesAdditionalLoginMethods
{
    use PasswordValidationRules;

    public string $newAccountPassword = '';

    public string $newAccountPassword_confirmation = '';

    #[Computed]
    public function hasGoogleLinked(): bool
    {
        return Auth::user()->google_id !== null;
    }

    #[Computed]
    public function hasPassword(): bool
    {
        return Auth::user()->password !== null;
    }

    /**
     * A password can only be set once the account's email is
     * independently verified — an unverified email is a live
     * "forgot password" takeover surface (Fortify's reset-password flow
     * would email whoever actually controls that inbox, not necessarily
     * this customer).
     */
    #[Computed]
    public function canSetPassword(): bool
    {
        return Auth::user()->hasVerifiedEmailAddress();
    }

    /**
     * Set the account's first password — no `current_password` field,
     * since one has never existed. `Security::updatePassword()` (which
     * does require it) takes over for every change after this.
     */
    public function setInitialPassword(): void
    {
        $user = Auth::user();

        if ($user->password !== null || ! $this->canSetPassword()) {
            return;
        }

        $validated = $this->validate([
            'newAccountPassword' => $this->passwordRules(),
        ]);

        $user->update(['password' => $validated['newAccountPassword']]);

        $this->reset('newAccountPassword', 'newAccountPassword_confirmation');
        unset($this->hasPassword);

        $this->dispatch('toast', variant: 'success', message: __('Password set. You can now also log in with your email and password.'));
    }
}
