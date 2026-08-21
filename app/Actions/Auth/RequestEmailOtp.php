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
 * the send side differs. Per-email limits are looser than SMS — email
 * carries no per-send cost, so this exists to blunt notification-spam
 * abuse, not to control spend. The per-IP cap exists for the same reason
 * RequestOtp has one: today's only call site always passes the caller's
 * own email, so it's not independently exploitable yet, but it closes
 * the gap in advance for any future call site that accepts a
 * caller-supplied address instead.
 */
class RequestEmailOtp
{
    use AsAction;

    /**
     * @throws OtpRateLimitedException when the email (or its source IP) has requested too many codes too recently
     */
    public function handle(string $email, string $purpose, string $reason, ?string $ip = null): void
    {
        $this->assertNotRateLimited($email, $ip);

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

        if ($ip !== null) {
            RateLimiter::hit("email-otp-request-ip-hour:{$ip}", 3600);
        }
    }

    /**
     * @throws OtpRateLimitedException
     */
    private function assertNotRateLimited(string $email, ?string $ip): void
    {
        if (RateLimiter::tooManyAttempts("email-otp-request-minute:{$email}", 1)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("email-otp-request-minute:{$email}"));
        }

        if (RateLimiter::tooManyAttempts("email-otp-request-hour:{$email}", 5)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("email-otp-request-hour:{$email}"));
        }

        if ($ip !== null && RateLimiter::tooManyAttempts("email-otp-request-ip-hour:{$ip}", 30)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("email-otp-request-ip-hour:{$ip}"));
        }
    }
}
