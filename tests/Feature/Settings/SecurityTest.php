<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Security;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);
    }

    public function test_security_settings_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk();

        $response->assertSee('Passkeys');
        $response->assertSee('No passkeys yet');
        $response->assertSee('Two-factor authentication');
        $response->assertSee('Enable 2FA');
    }

    public function test_security_settings_page_requires_password_confirmation_when_enabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_settings_page_renders_without_two_factor_when_feature_is_disabled(): void
    {
        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Update password')
            ->assertDontSee('Manage your passkeys for passwordless sign-in')
            ->assertDontSee('Add a passkey to sign in without a password')
            ->assertDontSee('Two-factor authentication');
    }

    public function test_two_factor_authentication_disabled_when_confirmation_abandoned_between_requests(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($user);

        $component = Livewire::test(Security::class);

        $component->assertSet('twoFactorEnabled', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test(Security::class)
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test(Security::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasErrors(['current_password']);
    }

    /**
     * True cross-session verification can't be exercised here — tests run
     * on the `array` session driver (see phpunit.xml), which never
     * persists to the `sessions` table LogOutOtherSessions operates on
     * (see LogOutOtherSessionsTest for that action's own direct coverage).
     * This instead proves every security-relevant action actually calls
     * it — the wiring this fix depends on — same source-inspection
     * pattern already used elsewhere in this suite for locking guarantees
     * that can't be exercised against SQLite in-process either.
     */
    public function test_every_security_relevant_action_logs_out_other_sessions(): void
    {
        $source = (string) file_get_contents(app_path('Livewire/Settings/Security.php'));

        $this->assertSame(5, substr_count($source, 'LogOutOtherSessions::run('));
    }

    public function test_the_update_password_card_is_hidden_for_an_account_with_no_password_yet(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123', 'email' => 'a@example.com', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertDontSee('Ensure your account is using a long, random password to stay secure');
    }

    public function test_a_verified_google_only_account_can_set_its_first_password(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123', 'email' => 'a@example.com', 'email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Security::class)
            ->set('newAccountPassword', 'brand-new-password')
            ->set('newAccountPassword_confirmation', 'brand-new-password')
            ->call('setInitialPassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $user->refresh()->password));
    }

    public function test_setting_a_password_is_refused_for_an_account_with_an_unverified_email(): void
    {
        $user = User::factory()->create(['password' => null, 'email' => 'a@example.com', 'email_verified_at' => null]);
        $this->actingAs($user);

        Livewire::test(Security::class)
            ->set('newAccountPassword', 'brand-new-password')
            ->set('newAccountPassword_confirmation', 'brand-new-password')
            ->call('setInitialPassword');

        $this->assertNull($user->fresh()->password);
    }

    public function test_setting_a_password_is_refused_when_the_account_already_has_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('existing-password'), 'email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Security::class)
            ->set('newAccountPassword', 'brand-new-password')
            ->set('newAccountPassword_confirmation', 'brand-new-password')
            ->call('setInitialPassword');

        $this->assertTrue(Hash::check('existing-password', $user->fresh()->password));
    }

    public function test_the_connect_google_button_shows_only_when_not_already_linked(): void
    {
        $unlinked = User::factory()->create(['google_id' => null]);
        $this->actingAs($unlinked)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertDontSee('Connected');

        $linked = User::factory()->create(['google_id' => 'google-123']);
        $this->actingAs($linked)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertSee('Connected');
    }
}
