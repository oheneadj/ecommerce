<?php

/**
 * Issues a one-time verification code to an email address.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\OtpRateLimitedException;
use App\Models\OtpCode;
use App\Notifications\AccountVerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The email counterpart to RequestOtp (phone+SMS) — for an account that
 * has no phone on file (e.g. Google-only) but still needs a real
 * re-verification step, not just its already-authenticated session.
 * Shares `ConsumeOtpCode`/`otp_codes` with the phone flow (rows are
 * scoped by identifier + purpose regardless of delivery channel), only
 * the send side differs. Rate limited far more loosely than SMS — email
 * carries no per-send cost, so this exists to blunt notification-spam
 * abuse, not to control spend.
 */
class RequestEmailOtp
{
    use AsAction;

    /**
     * @throws OtpRateLimitedException when the email has requested too many codes too recently
     */
    public function handle(string $email, string $purpose, string $reason): void
    {
        $this->assertNotRateLimited($email);

        $code = (string) random_int(100000, 999999);

        OtpCode::query()->create([
            'identifier' => $email,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(10),
        ]);

        Notification::route('mail', $email)->notify(new AccountVerificationCode($code, $reason));

        RateLimiter::hit("email-otp-request-minute:{$email}", 60);
        RateLimiter::hit("email-otp-request-hour:{$email}", 3600);
    }

    /**
     * @throws OtpRateLimitedException
     */
    private function assertNotRateLimited(string $email): void
    {
        if (RateLimiter::tooManyAttempts("email-otp-request-minute:{$email}", 1)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("email-otp-request-minute:{$email}"));
        }

        if (RateLimiter::tooManyAttempts("email-otp-request-hour:{$email}", 5)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("email-otp-request-hour:{$email}"));
        }
    }
}
