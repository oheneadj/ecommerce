<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\RequestOtp;
use App\Actions\Auth\VerifyOtp;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpRateLimitedException;
use App\Models\OtpCode;
use App\Models\User;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_requesting_an_otp_twice_within_60_seconds_is_rate_limited(): void
    {
        $this->fakeSmsGateway();

        RequestOtp::run('+233201234567');

        $this->expectException(OtpRateLimitedException::class);

        RequestOtp::run('+233201234567');
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
}
