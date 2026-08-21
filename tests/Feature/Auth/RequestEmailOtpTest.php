<?php

/**
 * Covers RequestEmailOtp — the email counterpart to phone+SMS OTP, used
 * wherever an account has no phone to receive a code on.
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\RequestEmailOtp;
use App\Exceptions\OtpRateLimitedException;
use App\Models\OtpCode;
use App\Notifications\AccountVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequestEmailOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_hashed_otp_code_scoped_to_the_given_purpose(): void
    {
        Notification::fake();

        RequestEmailOtp::run('shopper@example.com', 'delete_account', 'Enter this code to confirm.');

        $otp = OtpCode::query()->where('identifier', 'shopper@example.com')->where('purpose', 'delete_account')->first();
        $this->assertNotNull($otp);
        $this->assertFalse(Hash::check('wrong-code', $otp->code_hash));
    }

    public function test_it_sends_the_verification_code_notification_to_the_given_email(): void
    {
        Notification::fake();

        RequestEmailOtp::run('shopper@example.com', 'delete_account', 'Enter this code to confirm.');

        Notification::assertSentOnDemand(AccountVerificationCode::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'shopper@example.com');
    }

    public function test_requesting_a_code_twice_within_60_seconds_is_rate_limited(): void
    {
        Notification::fake();

        RequestEmailOtp::run('shopper@example.com', 'delete_account', 'Enter this code to confirm.');

        $this->expectException(OtpRateLimitedException::class);

        RequestEmailOtp::run('shopper@example.com', 'delete_account', 'Enter this code to confirm.');
    }

    public function test_the_rate_limit_is_scoped_per_email(): void
    {
        Notification::fake();

        RequestEmailOtp::run('shopper@example.com', 'delete_account', 'Enter this code to confirm.');

        // A different email is unaffected by the first email's rate limit.
        RequestEmailOtp::run('other@example.com', 'delete_account', 'Enter this code to confirm.');

        Notification::assertSentOnDemandTimes(AccountVerificationCode::class, 2);
    }
}
