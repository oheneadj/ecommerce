<?php

/**
 * Verifies a login OTP code and authenticates (or creates) the phone's user account.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        // Deliberately outside the transaction below: a failed attempt
        // (wrong code) must still persist its incremented attempts count
        // even though nothing else happens — wrapping it in the same
        // transaction as user creation would roll that increment back
        // the moment ConsumeOtpCode throws, silently defeating the
        // per-code attempt lockout.
        ConsumeOtpCode::run($phone, $code, 'login');

        $user = DB::transaction(function () use ($phone): User {
            $user = User::query()->firstOrCreate(['phone' => $phone]);

            if ($user->phone_verified_at === null) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            return $user;
        });

        Auth::login($user, remember: true);

        return $user;
    }
}
