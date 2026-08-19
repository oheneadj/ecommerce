<?php

/**
 * Covers the full-system-bug-hunt fix: disabled_at previously only gated
 * the Filament admin panel — a disabled customer account could still log
 * in via password, phone OTP, or Google with no effect at all.
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LoginWithGoogle;
use App\Actions\Auth\VerifyOtp;
use App\Exceptions\AccountDisabledException;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class DisabledAccountLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_disabled_account_cannot_log_in_with_a_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('password'),
            'disabled_at' => now(),
        ]);

        $this->post('/login', [
            'email' => 'disabled@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_enabled_account_can_still_log_in_with_a_correct_password(): void
    {
        User::factory()->create([
            'email' => 'active@example.com',
            'password' => Hash::make('password'),
            'disabled_at' => null,
        ]);

        $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_a_disabled_account_cannot_log_in_via_phone_otp(): void
    {
        $user = User::factory()->create(['phone' => '+233201234567', 'disabled_at' => now()]);
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->expectException(AccountDisabledException::class);

        try {
            VerifyOtp::run('+233201234567', '123456');
        } finally {
            $this->assertGuest();
        }
    }

    public function test_a_disabled_account_cannot_log_in_via_google_by_google_id(): void
    {
        User::factory()->create(['google_id' => 'google-123', 'disabled_at' => now()]);

        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-123';
        $googleUser->email = 'disabled@example.com';
        $googleUser->name = 'Disabled Customer';

        $this->expectException(AccountDisabledException::class);

        try {
            LoginWithGoogle::run($googleUser);
        } finally {
            $this->assertGuest();
        }
    }

    public function test_a_disabled_account_cannot_log_in_via_google_by_verified_email_match(): void
    {
        User::factory()->create(['email' => 'disabled@example.com', 'email_verified_at' => now(), 'google_id' => null, 'disabled_at' => now()]);

        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-456';
        $googleUser->email = 'disabled@example.com';
        $googleUser->name = 'Disabled Customer';

        $this->expectException(AccountDisabledException::class);

        try {
            LoginWithGoogle::run($googleUser);
        } finally {
            $this->assertGuest();
        }
    }

    public function test_the_unauthenticated_google_callback_shows_a_friendly_message_for_a_disabled_account(): void
    {
        User::factory()->create(['google_id' => 'google-123', 'disabled_at' => now()]);

        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-123';
        $googleUser->email = 'disabled@example.com';
        $googleUser->name = 'Disabled Customer';

        Socialite::shouldReceive('driver->user')->andReturn($googleUser);

        $this->get('/login/google/callback')
            ->assertRedirect(route('login.phone'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }
}
