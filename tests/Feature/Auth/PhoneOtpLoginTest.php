<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\RequestOtp;
use App\Actions\Auth\VerifyOtp;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpRateLimitedException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use App\Models\OtpCode;
use App\Models\SmsApiLog;
use App\Models\User;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhoneOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSmsGateway(): void
    {
        $this->app->bind(SmsGateway::class, fn () => new class implements SmsGateway
        {
            public function send(string $to, string $message): SmsSendResult
            {
                return new SmsSendResult(success: true, providerReference: 'fake-ref');
            }
        });
    }

    public function test_requesting_an_otp_creates_a_hashed_code_and_never_stores_plaintext(): void
    {
        $this->fakeSmsGateway();

        RequestOtp::run('+233201234567');

        $otp = OtpCode::query()->where('identifier', '+233201234567')->first();

        $this->assertNotNull($otp);
        $this->assertMatchesRegularExpression('/^\$2y\$/', $otp->code_hash);
    }

    public function test_requesting_an_otp_records_an_sms_api_log_entry(): void
    {
        $this->fakeSmsGateway();

        RequestOtp::run('+233201234567');

        $log = SmsApiLog::query()->where('recipient', '+233201234567')->first();

        $this->assertNotNull($log);
        $this->assertSame('otp', $log->action);
        $this->assertStringContainsString('login code', $log->request_payload['message']);
    }

    public function test_the_sms_api_log_payload_is_encrypted_at_rest(): void
    {
        $this->fakeSmsGateway();

        RequestOtp::run('+233201234567');

        $log = SmsApiLog::query()->where('recipient', '+233201234567')->first();
        $raw = DB::table('sms_api_logs')->where('id', $log->id)->first();

        $this->assertStringNotContainsString('login code', $raw->request_payload);
    }

    public function test_requesting_an_otp_twice_within_60_seconds_is_rate_limited(): void
    {
        $this->fakeSmsGateway();

        RequestOtp::run('+233201234567');

        $this->expectException(OtpRateLimitedException::class);

        RequestOtp::run('+233201234567');
    }

    public function test_rotating_phone_numbers_from_the_same_ip_is_still_rate_limited(): void
    {
        // The per-phone limit alone doesn't stop an attacker driving
        // unlimited paid SMS sends by rotating through different phone
        // numbers from a single source — each fresh number has its own
        // clean per-phone counter.
        $this->fakeSmsGateway();

        for ($i = 0; $i < 30; $i++) {
            RequestOtp::run('+23320000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), '10.0.0.1');
        }

        $this->expectException(OtpRateLimitedException::class);

        RequestOtp::run('+233209999999', '10.0.0.1');
    }

    public function test_a_different_ip_is_not_affected_by_another_ips_otp_requests(): void
    {
        $this->fakeSmsGateway();

        for ($i = 0; $i < 30; $i++) {
            RequestOtp::run('+23320000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), '10.0.0.1');
        }

        // A different IP requesting a brand-new phone number is unaffected.
        RequestOtp::run('+233208888888', '10.0.0.2');

        $this->assertNotNull(OtpCode::query()->where('identifier', '+233208888888')->first());
    }

    public function test_verifying_a_new_phone_number_creates_and_logs_in_a_new_user(): void
    {
        $otp = OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $user = VerifyOtp::run('+233201234567', '123456');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('+233201234567', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertNotNull($otp->fresh()->consumed_at);
    }

    public function test_verifying_an_existing_phone_number_logs_in_the_same_user_without_creating_a_duplicate(): void
    {
        $existing = User::factory()->create(['phone' => '+233201234567', 'phone_verified_at' => now()]);

        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('654321'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $user = VerifyOtp::run('+233201234567', '654321');

        $this->assertSame($existing->id, $user->id);
        $this->assertSame(1, User::query()->where('phone', '+233201234567')->count());
    }

    public function test_verifying_with_the_wrong_code_fails_and_increments_attempts(): void
    {
        $otp = OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->expectException(InvalidOtpException::class);

        try {
            VerifyOtp::run('+233201234567', '000000');
        } finally {
            $this->assertSame(1, $otp->fresh()->attempts);
            $this->assertGuest();
        }
    }

    public function test_a_code_is_locked_out_after_five_failed_attempts(): void
    {
        $otp = OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);
        $otp->forceFill(['attempts' => 5])->save();

        $this->expectException(InvalidOtpException::class);

        VerifyOtp::run('+233201234567', '123456');
    }

    public function test_an_expired_code_cannot_be_verified(): void
    {
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(InvalidOtpException::class);

        VerifyOtp::run('+233201234567', '123456');
    }

    public function test_an_already_consumed_code_cannot_be_reused(): void
    {
        $otp = OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);
        $otp->forceFill(['consumed_at' => now()])->save();

        $this->expectException(InvalidOtpException::class);

        VerifyOtp::run('+233201234567', '123456');
    }

    /**
     * The per-code 5-attempt lock (OtpCode::isUsable()) resets the moment
     * a fresh code is requested — this caps verification attempts across
     * an entire rolling window instead, regardless of how many codes were
     * requested in between.
     */
    public function test_too_many_verification_attempts_for_a_phone_is_rate_limited_even_across_codes(): void
    {
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        for ($i = 0; $i < 10; $i++) {
            try {
                VerifyOtp::run('+233201234567', '000000');
            } catch (InvalidOtpException) {
                // Expected — wrong code, but still counts toward the limit.
            }
        }

        $this->expectException(TooManyOtpVerificationAttemptsException::class);

        VerifyOtp::run('+233201234567', '123456');
    }

    /**
     * The "Continue with Google" button was text-only, easy to miss next
     * to the phone-number form — added Google's brand mark so it reads as
     * a recognizable social-login button at a glance.
     */
    public function test_the_phone_login_page_shows_a_google_icon_next_to_continue_with_google(): void
    {
        $this->get(route('login.phone'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSeeHtml('viewBox="0 0 488 512"');
    }
}
