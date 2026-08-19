<?php

/**
 * Customer-facing phone + OTP login/registration component.
 */

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\RequestOtp;
use App\Actions\Auth\VerifyOtp;
use App\Exceptions\AccountDisabledException;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpRateLimitedException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use App\Livewire\Concerns\NormalizesPhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Two-step form: request a code for a phone number, then verify it. A new
 * phone number automatically creates an account on successful verification —
 * there is no separate registration step for this path (BRD FR-0.6).
 */
#[Title('Log in')]
class PhoneLogin extends Component
{
    use NormalizesPhoneNumber;

    public string $phone = '';

    public string $code = '';

    public bool $codeSent = false;

    /**
     * `codeSent`/`phone` are otherwise plain in-memory component
     * properties — a real page reload (the user's own refresh, or a
     * mobile browser discarding a backgrounded tab) would silently
     * re-mount this component from scratch and strand the customer back
     * on the phone-entry step, even though their already-requested code
     * is still valid server-side. The session is the one thing that
     * survives a reload, so it's what carries "which step am I on, for
     * which number" across one.
     */
    public function mount(): void
    {
        $pendingPhone = session('otp_login_phone');

        if (is_string($pendingPhone)) {
            $this->phone = $pendingPhone;
            $this->codeSent = true;
        }
    }

    /**
     * Send a login code to the given phone number.
     */
    public function sendCode(): void
    {
        if (! $this->normalizePhoneOrFail('phone', 'phone')) {
            return;
        }

        try {
            RequestOtp::run($this->phone, request()->ip());
        } catch (OtpRateLimitedException $e) {
            $this->addError('phone', $e->getMessage());

            return;
        }

        session(['otp_login_phone' => $this->phone]);
        $this->codeSent = true;
    }

    /**
     * Verify the submitted code and log the customer in (creating the account on first login).
     */
    public function verify(): void
    {
        Validator::make(['code' => $this->code], ['code' => ['required', 'digits:6']])->validate();

        try {
            VerifyOtp::run($this->phone, $this->code);
        } catch (InvalidOtpException|TooManyOtpVerificationAttemptsException|AccountDisabledException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->forget('otp_login_phone');

        $this->redirectIntended(default: route('account.show', absolute: false), navigate: true);
    }

    /**
     * Explicit escape hatch back to the phone-entry step — the only other
     * way `otp_login_phone` gets cleared besides a successful login.
     */
    public function useDifferentNumber(): void
    {
        session()->forget('otp_login_phone');
        $this->codeSent = false;
        $this->code = '';
    }

    public function render(): View
    {
        return view('livewire.auth.phone-login');
    }
}
