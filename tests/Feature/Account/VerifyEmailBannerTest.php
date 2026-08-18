<?php

/**
 * Covers the account-wide "verify your email" reminder banner, embedded once in x-account.layout.
 */

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Livewire\Account\VerifyEmailBanner;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class VerifyEmailBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_for_an_account_with_an_unverified_email(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'email_verified_at' => null]);
        $this->actingAs($user);

        Livewire::test(VerifyEmailBanner::class)->assertSet('shouldShow', true);
    }

    public function test_it_stays_hidden_for_a_verified_email(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(VerifyEmailBanner::class)->assertSet('shouldShow', false);
    }

    public function test_it_stays_hidden_for_a_phone_only_account_with_no_email(): void
    {
        $user = User::factory()->create(['email' => null, 'email_verified_at' => null, 'phone' => '+233201234567']);
        $this->actingAs($user);

        Livewire::test(VerifyEmailBanner::class)->assertSet('shouldShow', false);
    }

    public function test_resend_sends_a_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.com', 'email_verified_at' => null]);
        $this->actingAs($user);

        Livewire::test(VerifyEmailBanner::class)->call('resend');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.com', 'email_verified_at' => null]);
        $this->actingAs($user);

        Livewire::test(VerifyEmailBanner::class)->call('resend')->call('resend');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_the_banner_appears_on_account_pages(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'email_verified_at' => null]);
        $this->actingAs($user);

        $this->get(route('profile.edit'))->assertSee(__('Your email address is unverified.'));
    }
}
