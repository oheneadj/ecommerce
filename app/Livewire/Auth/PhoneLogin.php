<?php

/**
 * Customer-facing phone + OTP login/registration component.
 */

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\RequestOtp;
use App\Actions\Auth\VerifyOtp;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpRateLimitedException;
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
    public string $phone = '';

    public string $code = '';

    public bool $codeSent = false;

    /**
     * Send a login code to the given phone number.
     */
    public function sendCode(): void
    {
        $this->validate(['phone' => ['required', 'string', 'min:9']]);

        try {
            RequestOtp::run($this->phone, request()->ip());
        } catch (OtpRateLimitedException $e) {
            $this->addError('phone', $e->getMessage());

            return;
        }

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
        } catch (InvalidOtpException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.phone-login');
    }
}
