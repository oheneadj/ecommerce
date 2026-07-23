<?php

/**
 * Issues a login OTP code to a phone number.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\OtpRateLimitedException;
use App\Models\OtpCode;
use App\Sms\Contracts\SmsGateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generates a 6-digit login code, hashes it into `otp_codes`, and sends the
 * plaintext code via SmsGateway. Enforces max 1 request per phone per 60
 * seconds and max 5 per hour, since each SMS costs money via Moolre.
 */
class RequestOtp
{
    use AsAction;

    public function __construct(private readonly SmsGateway $sms) {}

    /**
     * @throws OtpRateLimitedException when the phone has requested too many codes too recently
     */
    public function handle(string $phone): void
    {
        $this->assertNotRateLimited($phone);

        $code = (string) random_int(100000, 999999);

        OtpCode::query()->create([
            'identifier' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->sms->send($phone, "Your login code is {$code}. It expires in 10 minutes.");

        RateLimiter::hit("otp-request-minute:{$phone}", 60);
        RateLimiter::hit("otp-request-hour:{$phone}", 3600);
    }

    /**
     * Enforce the combined 1-per-60s and 5-per-hour cost/abuse limits.
     *
     * @throws OtpRateLimitedException
     */
    private function assertNotRateLimited(string $phone): void
    {
        if (RateLimiter::tooManyAttempts("otp-request-minute:{$phone}", 1)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("otp-request-minute:{$phone}"));
        }

        if (RateLimiter::tooManyAttempts("otp-request-hour:{$phone}", 5)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("otp-request-hour:{$phone}"));
        }
    }
}
