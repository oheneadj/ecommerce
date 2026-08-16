<?php

namespace App\Livewire\Settings;

use App\Actions\Auth\LinkPhoneToAccount;
use App\Actions\Auth\RequestOtp;
use App\Concerns\ProfileValidationRules;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpRateLimitedException;
use App\Exceptions\PhoneAlreadyLinkedException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
#[Layout('layouts.storefront')]
class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $newPhone = '';

    public string $phoneOtpCode = '';

    public bool $phoneCodeSent = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        // Both columns are nullable — a phone+OTP customer's account starts
        // with no name at all, and no email until they explicitly add one.
        // Assigning a null model value straight into these typed string
        // properties would throw before the page ever rendered.
        $this->name = Auth::user()->name ?? '';
        $this->email = Auth::user()->email ?? '';
    }

    /**
     * Send a verification code to the phone number the customer wants to add.
     */
    public function sendPhoneVerificationCode(): void
    {
        $this->validate([
            'newPhone' => ['required', 'string', 'min:9', Rule::unique('users', 'phone')],
        ]);

        try {
            RequestOtp::run($this->newPhone, request()->ip(), 'link_phone');
        } catch (OtpRateLimitedException $e) {
            $this->addError('newPhone', $e->getMessage());

            return;
        }

        $this->phoneCodeSent = true;
    }

    /**
     * Verify the submitted code and attach the phone number to this account.
     */
    public function verifyPhoneCode(): void
    {
        $this->validate(['phoneOtpCode' => ['required', 'digits:6']]);

        try {
            LinkPhoneToAccount::run(Auth::user(), $this->newPhone, $this->phoneOtpCode);
        } catch (InvalidOtpException|TooManyOtpVerificationAttemptsException|PhoneAlreadyLinkedException $e) {
            $this->addError('phoneOtpCode', $e->getMessage());

            return;
        }

        $this->reset('newPhone', 'phoneOtpCode', 'phoneCodeSent');
        $this->dispatch('toast', variant: 'success', message: __('Phone number verified and added to your account.'));
    }

    /**
     * Back out of phone verification to the number-entry step.
     */
    public function cancelPhoneVerification(): void
    {
        $this->reset('phoneOtpCode', 'phoneCodeSent');
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('toast', variant: 'success', message: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('account.show', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        $this->dispatch('toast', message: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        $user = Auth::user();

        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        $user = Auth::user();

        return ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail();
    }

    /**
     * The account's already-verified phone number, if it has one.
     */
    #[Computed]
    public function verifiedPhone(): ?string
    {
        return Auth::user()->phone;
    }
}
