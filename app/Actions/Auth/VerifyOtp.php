<?php

/**
 * Verifies a login OTP code and authenticates (or creates) the phone's user account.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Registration and login are the same flow for phone+OTP: a new phone number
 * automatically creates an account on first successful verification, no
 * separate registration form required. Locks the code after 5 failed
 * attempts (OtpCode::isUsable()) — but that lock resets the moment a fresh
 * code is requested, so a 10-per-10-minutes per-phone cap is enforced here
 * too, closing the gap where repeated request-then-guess cycles could
 * otherwise bypass the per-code lock (RequestOtp already caps how many
 * codes can be requested in the first place, at 5/hour).
 */
class VerifyOtp
{
    use AsAction;

    /**
     * @throws InvalidOtpException when no usable code exists, it doesn't match, or it's locked out
     * @throws TooManyOtpVerificationAttemptsException when the phone has attempted verification too many times recently
     */
    public function handle(string $phone, string $code): User
    {
        $this->assertNotRateLimited($phone);

        RateLimiter::hit("otp-verify:{$phone}", 600);

        $otp = OtpCode::query()
            ->where('identifier', $phone)
            ->where('purpose', 'login')
            ->latest('id')
            ->first();

        if (! $otp || ! $otp->isUsable()) {
            throw new InvalidOtpException;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw new InvalidOtpException;
        }

        // Two rows (the OTP code and the User) that must move together —
        // consuming the code without the user ending up created/verified
        // (or vice versa) would leave either a burnt code with no
        // account, or a "verified" account whose code was never marked
        // consumed and could in principle be reused.
        $user = DB::transaction(function () use ($otp, $phone): User {
            $otp->forceFill(['consumed_at' => now()])->save();

            $user = User::query()->firstOrCreate(['phone' => $phone]);

            if ($user->phone_verified_at === null) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            return $user;
        });

        Auth::login($user, remember: true);

        return $user;
    }

    /**
     * @throws TooManyOtpVerificationAttemptsException
     */
    private function assertNotRateLimited(string $phone): void
    {
        if (RateLimiter::tooManyAttempts("otp-verify:{$phone}", 10)) {
            throw new TooManyOtpVerificationAttemptsException(RateLimiter::availableIn("otp-verify:{$phone}"));
        }
    }
}
