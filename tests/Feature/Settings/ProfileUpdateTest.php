<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Profile;
use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
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

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/settings/profile')->assertOk();
    }

    public function test_a_phone_only_customer_with_no_name_or_email_can_view_the_profile_page(): void
    {
        $user = User::factory()->create(['name' => null, 'email' => null, 'phone' => '+233201234567']);
        $this->actingAs($user);

        $this->get('/settings/profile')->assertOk();

        Livewire::test(Profile::class)
            ->assertSet('name', '')
            ->assertSet('email', '');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_adding_an_email_sends_a_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => null]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        Notification::assertSentTo($user->fresh(), QueuedVerifyEmail::class);
    }

    public function test_changing_an_already_verified_email_sends_a_fresh_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('name', $user->name)
            ->set('email', 'new@example.com')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user->fresh(), QueuedVerifyEmail::class);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_user_can_delete_their_account(): void
    {
        // Soft-deleted, not hard-deleted (so past orders/reviews still
        // resolve a name) — but email/phone/google_id are freed so the
        // customer can register a new account with the same one later.
        $user = User::factory()->create(['email' => 'shopper@example.com', 'phone' => '+233201234567']);
        $id = $user->id;

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertFalse(auth()->check());
        $this->assertNull(User::query()->find($id));

        $trashed = User::withTrashed()->find($id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        $this->assertSame("shopper@example.com-deleted-{$id}", $trashed->email);
        $this->assertSame("+233201234567-deleted-{$id}", $trashed->phone);
    }

    public function test_a_new_account_can_reuse_the_email_and_phone_of_a_deleted_account(): void
    {
        $user = User::factory()->create(['email' => 'shopper@example.com', 'phone' => '+233201234567']);

        $this->actingAs($user);
        Livewire::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $newUser = User::factory()->create(['email' => 'shopper@example.com', 'phone' => '+233201234567']);

        $this->assertSame('shopper@example.com', $newUser->email);
        $this->assertNotSame($user->id, $newUser->id);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }

    public function test_an_email_password_customer_can_request_a_code_to_add_a_phone_number(): void
    {
        $this->fakeSmsGateway();
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('newPhone', '+233201234567')
            ->call('sendPhoneVerificationCode')
            ->assertHasNoErrors()
            ->assertSet('phoneCodeSent', true);

        $this->assertNotNull(OtpCode::query()->where('identifier', '+233201234567')->where('purpose', 'link_phone')->first());
    }

    public function test_requesting_a_code_for_a_phone_already_used_by_another_account_is_rejected(): void
    {
        $this->fakeSmsGateway();
        User::factory()->create(['phone' => '+233201234567']);
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('newPhone', '+233201234567')
            ->call('sendPhoneVerificationCode')
            ->assertHasErrors(['newPhone']);
    }

    public function test_verifying_the_correct_code_attaches_the_phone_number_to_the_account(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'link_phone',
            'expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(Profile::class)
            ->set('newPhone', '+233201234567')
            ->set('phoneOtpCode', '123456')
            ->call('verifyPhoneCode')
            ->assertHasNoErrors()
            ->assertSet('phoneCodeSent', false);

        $user->refresh();
        $this->assertSame('+233201234567', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_a_verified_phone_is_shown_without_the_add_form(): void
    {
        $user = User::factory()->create(['phone' => '+233201234567', 'phone_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->assertSee('+233201234567')
            ->assertSet('changingPhone', false);
    }

    public function test_a_customer_with_a_verified_phone_can_start_changing_it(): void
    {
        $this->fakeSmsGateway();
        $user = User::factory()->create(['phone' => '+233201234567', 'phone_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->call('startPhoneChange')
            ->assertSet('changingPhone', true)
            ->set('newPhone', '+233209999999')
            ->call('sendPhoneVerificationCode')
            ->assertHasNoErrors()
            ->assertSet('phoneCodeSent', true);
    }

    public function test_verifying_a_new_code_replaces_the_existing_verified_phone(): void
    {
        $user = User::factory()->create(['phone' => '+233201234567', 'phone_verified_at' => now()]);
        $this->actingAs($user);
        OtpCode::query()->create([
            'identifier' => '+233209999999',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'link_phone',
            'expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(Profile::class)
            ->call('startPhoneChange')
            ->set('newPhone', '+233209999999')
            ->set('phoneOtpCode', '123456')
            ->call('verifyPhoneCode')
            ->assertHasNoErrors()
            ->assertSet('changingPhone', false);

        $this->assertSame('+233209999999', $user->refresh()->phone);
    }

    public function test_cancelling_a_phone_change_returns_to_the_verified_number_display(): void
    {
        $user = User::factory()->create(['phone' => '+233201234567', 'phone_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->call('startPhoneChange')
            ->assertSet('changingPhone', true)
            ->call('cancelPhoneChange')
            ->assertSet('changingPhone', false);
    }

    public function test_verifying_the_wrong_code_does_not_attach_the_phone_number(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'link_phone',
            'expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(Profile::class)
            ->set('newPhone', '+233201234567')
            ->set('phoneOtpCode', '000000')
            ->call('verifyPhoneCode')
            ->assertHasErrors(['phoneOtpCode']);

        $this->assertNull($user->refresh()->phone);
    }

    public function test_reloading_the_page_after_sending_a_phone_code_stays_on_the_verify_step(): void
    {
        $this->fakeSmsGateway();
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('newPhone', '+233201234567')
            ->call('sendPhoneVerificationCode')
            ->assertSet('phoneCodeSent', true);

        // A fresh component instance, exactly as a real page reload
        // produces — mount() must restore state from the session alone.
        Livewire::test(Profile::class)
            ->assertSet('phoneCodeSent', true)
            ->assertSet('newPhone', '+233201234567');
    }

    public function test_using_a_different_number_clears_the_pending_phone_session_state(): void
    {
        $this->fakeSmsGateway();
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('newPhone', '+233201234567')
            ->call('sendPhoneVerificationCode')
            ->assertSet('phoneCodeSent', true)
            ->call('cancelPhoneVerification')
            ->assertSet('phoneCodeSent', false);

        Livewire::test(Profile::class)->assertSet('phoneCodeSent', false);
    }

    public function test_a_login_otp_code_cannot_be_used_to_link_a_phone_number(): void
    {
        // The purpose scoping on OtpCode must isolate these two flows —
        // a code sent for a totally different phone login must never
        // double as proof of ownership for this linking flow.
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);
        OtpCode::query()->create([
            'identifier' => '+233201234567',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        Livewire::test(Profile::class)
            ->set('newPhone', '+233201234567')
            ->set('phoneOtpCode', '123456')
            ->call('verifyPhoneCode')
            ->assertHasErrors(['phoneOtpCode']);

        $this->assertNull($user->refresh()->phone);
    }

    public function test_a_phone_only_verified_account_can_set_its_first_password(): void
    {
        $user = User::factory()->create(['password' => null, 'phone' => '+233201234567', 'email' => 'a@example.com', 'email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('newAccountPassword', 'brand-new-password')
            ->set('newAccountPassword_confirmation', 'brand-new-password')
            ->call('setInitialPassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $user->refresh()->password));
    }

    public function test_the_password_form_is_hidden_until_email_is_verified(): void
    {
        $user = User::factory()->create(['password' => null, 'email' => 'a@example.com', 'email_verified_at' => null]);
        $this->actingAs($user);

        $this->get('/settings/profile')
            ->assertOk()
            ->assertSee('Verify your email above to enable password sign-in.')
            ->assertDontSee('Set password');
    }

    public function test_the_connect_google_button_shows_only_when_not_already_linked(): void
    {
        $unlinked = User::factory()->create(['google_id' => null]);
        $this->actingAs($unlinked)->get('/settings/profile')->assertDontSee('Connected');

        $linked = User::factory()->create(['google_id' => 'google-123']);
        $this->actingAs($linked)->get('/settings/profile')->assertSee('Connected');
    }
}
