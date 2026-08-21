<?php

/**
 * Covers the customer-facing "delete my account" settings form.
 */

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Livewire\Settings\DeleteUserForm;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteUserFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_password_account_requires_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertFalse($user->fresh()->trashed());
    }

    public function test_a_password_account_can_delete_itself_with_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'correct-password')
            ->call('deleteUser');

        $this->assertTrue($user->fresh()->trashed());
    }

    /**
     * Regression: a phone-OTP-only or Google-only account never has a
     * password (`users.password` is null) — Laravel's `current_password`
     * rule always fails against a null hash, which made self-service
     * deletion permanently unreachable for that account. An account with
     * a phone on file now re-verifies via a fresh SMS OTP instead of a
     * password.
     */
    public function test_a_password_less_account_with_a_phone_requires_a_correct_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => null, 'phone' => '+233201234567', 'google_id' => 'google-123']);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->assertSet('otpChannel', 'phone')
            ->call('sendDeletionCode')
            ->assertSet('otpSent', true);

        $otp = OtpCode::query()->where('identifier', '+233201234567')->where('purpose', 'delete_account')->first();
        $this->assertNotNull($otp);
    }

    public function test_a_password_less_account_with_a_phone_cannot_delete_itself_with_a_wrong_code(): void
    {
        $user = User::factory()->create(['password' => null, 'phone' => '+233201234567']);
        $this->actingAs($user);
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'delete_account',
            'expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(DeleteUserForm::class)
            ->set('otpCode', '000000')
            ->call('deleteUser')
            ->assertHasErrors(['otpCode']);

        $this->assertFalse($user->fresh()->trashed());
    }

    public function test_a_password_less_account_with_a_phone_can_delete_itself_with_the_correct_code(): void
    {
        $user = User::factory()->create(['password' => null, 'phone' => '+233201234567']);
        $this->actingAs($user);
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'delete_account',
            'expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(DeleteUserForm::class)
            ->set('otpCode', '123456')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->trashed());
    }

    /**
     * A code minted for a different purpose (e.g. login) must never
     * confirm a deletion — ConsumeOtpCode scopes every lookup by purpose.
     */
    public function test_a_code_issued_for_a_different_purpose_does_not_confirm_deletion(): void
    {
        $user = User::factory()->create(['password' => null, 'phone' => '+233201234567']);
        $this->actingAs($user);
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(DeleteUserForm::class)
            ->set('otpCode', '123456')
            ->call('deleteUser')
            ->assertHasErrors(['otpCode']);

        $this->assertFalse($user->fresh()->trashed());
    }

    /**
     * A Google-only account has no phone, but still has a verified email
     * (Google's own OAuth handshake independently confirms it) — the code
     * goes there instead, rather than falling all the way back to a typed
     * confirmation phrase.
     */
    public function test_a_password_less_account_with_no_phone_but_a_verified_email_requires_a_correct_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'password' => null,
            'phone' => null,
            'google_id' => 'google-123',
            'email' => 'shopper@example.com',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->assertSet('otpChannel', 'mail')
            ->call('sendDeletionCode')
            ->assertSet('otpSent', true);

        $otp = OtpCode::query()->where('identifier', 'shopper@example.com')->where('purpose', 'delete_account')->first();
        $this->assertNotNull($otp);
    }

    public function test_a_password_less_account_with_no_phone_can_delete_itself_with_the_correct_email_code(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'phone' => null,
            'google_id' => 'google-123',
            'email' => 'shopper@example.com',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        OtpCode::query()->create([
            'identifier' => 'shopper@example.com',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'delete_account',
            'expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(DeleteUserForm::class)
            ->set('otpCode', '123456')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->trashed());
    }

    /**
     * The rare true no-channel edge case: no password, no phone, and no
     * verified email (e.g. an unverified Google email that collided with
     * an existing account's, so LoginWithGoogle never even stored it).
     * Falls back to a typed confirmation phrase rather than silently
     * letting the logged-in session alone be enough.
     */
    public function test_a_password_less_account_with_no_channel_at_all_requires_the_confirmation_phrase(): void
    {
        $user = User::factory()->create(['password' => null, 'phone' => null, 'email' => null, 'email_verified_at' => null, 'google_id' => 'google-123']);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->assertSet('canReceiveCode', false)
            ->call('deleteUser')
            ->assertHasErrors(['confirmationPhrase']);

        $this->assertFalse($user->fresh()->trashed());
    }

    public function test_a_password_less_account_with_no_channel_at_all_can_delete_itself_by_typing_delete(): void
    {
        $user = User::factory()->create(['password' => null, 'phone' => null, 'email' => null, 'email_verified_at' => null, 'google_id' => 'google-123']);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->set('confirmationPhrase', 'DELETE')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->trashed());
    }

    public function test_the_password_field_is_hidden_for_a_password_less_account(): void
    {
        $user = User::factory()->create(['password' => null, 'phone' => '+233201234567', 'google_id' => 'google-123']);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->assertSet('hasPassword', false)
            ->assertDontSeeHtml('wire:model="password"');
    }
}
