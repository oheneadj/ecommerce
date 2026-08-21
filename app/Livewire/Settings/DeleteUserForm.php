<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Auth\ConsumeOtpCode;
use App\Actions\Auth\DeleteAccount;
use App\Actions\Auth\RequestOtp;
use App\Concerns\PasswordValidationRules;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpRateLimitedException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Storefront settings form for a customer to delete their own account.
 */
class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    private const OTP_PURPOSE = 'delete_account';

    public string $password = '';

    public string $otpCode = '';

    public bool $otpSent = false;

    public string $confirmationPhrase = '';

    /**
     * A phone-OTP-only or Google-only customer never has a password
     * (users.password is null) — Laravel's `current_password` rule always
     * fails against a null hash, which used to make self-service deletion
     * permanently unreachable for that account. A re-sent OTP (for an
     * account with a phone) or a typed confirmation phrase (for one
     * without) replaces the password check for these accounts — being
     * logged in alone isn't an equivalent bar, the same reasoning a
     * password-account's re-entered password exists for in the first
     * place.
     */
    #[Computed]
    public function hasPassword(): bool
    {
        return Auth::user()?->password !== null;
    }

    /**
     * Whether a code can be sent to re-verify this account — only an
     * account with a verified phone has a channel to receive one.
     */
    #[Computed]
    public function hasPhone(): bool
    {
        return Auth::user()?->phone !== null;
    }

    /**
     * Sends a fresh deletion-confirmation OTP to the account's own phone,
     * scoped to its own `delete_account` purpose so it can never be
     * confused with (or reused as) a login or phone-linking code.
     */
    public function sendDeletionCode(): void
    {
        $user = Auth::user();

        try {
            RequestOtp::run($user->phone, request()->ip(), self::OTP_PURPOSE);
        } catch (OtpRateLimitedException $e) {
            $this->addError('otpCode', $e->getMessage());

            return;
        }

        $this->otpSent = true;
    }

    /**
     * Delete the currently authenticated user's account. The actual
     * deletion logic (soft delete + freeing the unique email/phone/
     * google_id for reuse) lives in DeleteAccount, not here — this
     * component is UI/HTTP glue only.
     */
    public function deleteUser(Logout $logout): void
    {
        $user = Auth::user();

        if ($this->hasPassword()) {
            $this->validate(['password' => $this->currentPasswordRules()]);
        } elseif ($this->hasPhone()) {
            $this->validate(['otpCode' => ['required', 'digits:6']]);

            try {
                ConsumeOtpCode::run($user->phone, $this->otpCode, self::OTP_PURPOSE);
            } catch (InvalidOtpException|TooManyOtpVerificationAttemptsException $e) {
                $this->addError('otpCode', $e->getMessage());

                return;
            }
        } else {
            // No password and no phone — a Google-only account with no
            // channel an OTP could be sent to. A typed confirmation
            // phrase is a weaker bar than a re-verified code, but it's
            // still real, deliberate friction beyond "the session happens
            // to still be logged in" — the same minimum this app already
            // asks for other rare, no-better-option destructive actions.
            $this->validate(['confirmationPhrase' => ['required', 'in:DELETE']], [
                'confirmationPhrase.in' => 'Please type DELETE to confirm.',
            ]);
        }

        $logout();
        DeleteAccount::run($user);

        $this->redirect('/', navigate: true);
    }
}
