<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Auth\LinkPhoneToAccount;
use App\Actions\Auth\RequestOtp;
use App\Concerns\ProfileValidationRules;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpRateLimitedException;
use App\Exceptions\PhoneAlreadyLinkedException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use App\Livewire\Concerns\ManagesAdditionalLoginMethods;
use App\Livewire\Concerns\NormalizesPhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Storefront settings page for editing name/email and adding or
 * changing a verified phone number via OTP.
 */
#[Title('Profile settings')]
#[Layout('layouts.storefront')]
class Profile extends Component
{
    use ManagesAdditionalLoginMethods;
    use NormalizesPhoneNumber;
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $newPhone = '';

    public string $phoneOtpCode = '';

    public bool $phoneCodeSent = false;

    /**
     * Reveals the add/change form even though a verified phone already
     * exists — otherwise the page just shows the current number.
     */
    public bool $changingPhone = false;

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

        // `newPhone`/`phoneCodeSent` are otherwise plain in-memory component
        // properties — a page reload while waiting for the code (the same
        // gap PhoneLogin already had to solve) would silently re-mount this
        // component and strand the customer back on the number-entry step,
        // even though their already-requested code is still valid
        // server-side. The session survives a reload; component state doesn't.
        $pendingPhone = session('link_phone_number');

        if (is_string($pendingPhone)) {
            $this->newPhone = $pendingPhone;
            $this->phoneCodeSent = true;
            $this->changingPhone = true;
        }
    }

    /**
     * Reveal the add/change form for a customer who already has a
     * verified phone number.
     */
    public function startPhoneChange(): void
    {
        $this->changingPhone = true;
    }

    /**
     * Back out of the number-entry step to the verified-number display —
     * only reached before a code has been sent (once sent, "use a
     * different number" via cancelPhoneVerification is the equivalent).
     */
    public function cancelPhoneChange(): void
    {
        $this->reset('newPhone', 'changingPhone');
    }

    /**
     * Send a verification code to the phone number the customer wants to add.
     */
    public function sendPhoneVerificationCode(): void
    {
        if (! $this->normalizePhoneOrFail('newPhone', 'newPhone')) {
            return;
        }

        $this->validate([
            'newPhone' => [Rule::unique('users', 'phone')->ignore(Auth::id())],
        ]);

        try {
            RequestOtp::run($this->newPhone, request()->ip(), 'link_phone');
        } catch (OtpRateLimitedException $e) {
            $this->addError('newPhone', $e->getMessage());

            return;
        }

        session(['link_phone_number' => $this->newPhone]);
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

        session()->forget('link_phone_number');
        $this->reset('newPhone', 'phoneOtpCode', 'phoneCodeSent', 'changingPhone');
        $this->dispatch('toast', variant: 'success', message: __('Phone number verified and saved to your account.'));
    }

    /**
     * Back out of phone verification to the number-entry step.
     */
    public function cancelPhoneVerification(): void
    {
        session()->forget('link_phone_number');
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

        // A changed email is unconfirmed until proven again — and needs a
        // fresh verification link sent to the new address, since the old
        // one (if any was ever sent) points at whatever the email used to
        // be. Skipped for a customer clearing back to no email at all,
        // since there's nothing to send to.
        $emailChangedToSomething = $user->isDirty('email') && $user->email !== null;

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChangedToSomething) {
            $user->sendEmailVerificationNotification();
        }

        $this->dispatch('toast', variant: 'success', message: __('Profile updated.'));
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
