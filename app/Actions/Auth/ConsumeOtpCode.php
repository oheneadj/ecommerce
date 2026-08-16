<?php

/**
 * Verifies and consumes a hashed OTP code for a given identifier + purpose.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The shared hash-check/attempts/expiry/rate-limit logic behind every OTP
 * verification, regardless of what the code is *for* — a login code and a
 * "link this phone to my account" code must never be interchangeable, so
 * every lookup is scoped to both `identifier` and `purpose`. What happens
 * once a code is confirmed valid (log the customer in vs. attach a phone
 * to an already-authenticated account) is purpose-specific and stays in
 * the caller.
 */
class ConsumeOtpCode
{
    use AsAction;

    /**
     * @throws InvalidOtpException when no usable code exists, it doesn't match, or it's locked out
     * @throws TooManyOtpVerificationAttemptsException when the identifier has attempted verification too many times recently
     */
    public function handle(string $identifier, string $code, string $purpose): void
    {
        $this->assertNotRateLimited($identifier);

        RateLimiter::hit("otp-verify:{$identifier}", 600);

        $otp = OtpCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $otp || ! $otp->isUsable()) {
            throw new InvalidOtpException;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw new InvalidOtpException;
        }

        $otp->forceFill(['consumed_at' => now()])->save();
    }

    /**
     * @throws TooManyOtpVerificationAttemptsException
     */
    private function assertNotRateLimited(string $identifier): void
    {
        if (RateLimiter::tooManyAttempts("otp-verify:{$identifier}", 10)) {
            throw new TooManyOtpVerificationAttemptsException(RateLimiter::availableIn("otp-verify:{$identifier}"));
        }
    }
}
