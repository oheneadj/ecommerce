<?php

/**
 * Issues a login OTP code to a phone number.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\OtpRateLimitedException;
use App\Models\OtpCode;
use App\Models\SmsApiLog;
use App\Notifications\Support\BrandedMessage;
use App\Sms\Contracts\SmsGateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generates a 6-digit login code, hashes it into `otp_codes`, and sends the
 * plaintext code via SmsGateway. Enforces max 1 request per phone per 60
 * seconds and max 5 per hour, since each SMS costs money via Moolre.
 *
 * The per-phone limit alone doesn't stop an attacker rotating through
 * many different phone numbers from a single source — each new number
 * starts with a clean per-phone counter — so a looser per-IP cap (30/hour)
 * is enforced alongside it, catching that abuse pattern without blocking
 * legitimate shared-IP scenarios (office wifi, NAT, a shop's shared kiosk).
 */
class RequestOtp
{
    use AsAction;

    public function __construct(private readonly SmsGateway $sms) {}

    /**
     * @throws OtpRateLimitedException when the phone (or its source IP) has requested too many codes too recently
     */
    public function handle(string $phone, ?string $ip = null, string $purpose = 'login'): void
    {
        $this->assertNotRateLimited($phone, $ip);

        $code = (string) random_int(100000, 999999);

        OtpCode::query()->create([
            'identifier' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(10),
        ]);

        $text = $purpose === 'login'
            ? "Your login code is {$code}. It expires in 10 minutes."
            : "Your phone verification code is {$code}. It expires in 10 minutes.";
        $message = BrandedMessage::sms($text);
        $result = $this->sms->send($phone, $message);

        SmsApiLog::query()->create([
            'provider' => 'moolre',
            'action' => 'otp',
            'recipient' => $phone,
            'request_payload' => ['recipient' => $phone, 'message' => $message],
            'response_payload' => $result->rawResponse,
            'status_code' => $result->statusCode,
        ]);

        RateLimiter::hit("otp-request-minute:{$phone}", 60);
        RateLimiter::hit("otp-request-hour:{$phone}", 3600);

        if ($ip !== null) {
            RateLimiter::hit("otp-request-ip-hour:{$ip}", 3600);
        }
    }

    /**
     * Enforce the combined 1-per-60s and 5-per-hour per-phone limits, plus
     * a 30-per-hour per-IP limit against phone-rotation abuse.
     *
     * @throws OtpRateLimitedException
     */
    private function assertNotRateLimited(string $phone, ?string $ip): void
    {
        if (RateLimiter::tooManyAttempts("otp-request-minute:{$phone}", 1)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("otp-request-minute:{$phone}"));
        }

        if (RateLimiter::tooManyAttempts("otp-request-hour:{$phone}", 5)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("otp-request-hour:{$phone}"));
        }

        if ($ip !== null && RateLimiter::tooManyAttempts("otp-request-ip-hour:{$ip}", 30)) {
            throw new OtpRateLimitedException(RateLimiter::availableIn("otp-request-ip-hour:{$ip}"));
        }
    }
}
