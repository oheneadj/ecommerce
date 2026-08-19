<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * Fortify's own route file only conditionally throttles login/2FA/
     * verification/passkeys — the "request a reset link" route carried no
     * throttle at all, letting an attacker flood a victim's inbox with
     * reset links, or probe which emails exist in the system, unthrottled.
     */
    public function test_requesting_a_reset_link_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.request'), ['email' => $user->email]);
        }

        $response = $this->post(route('password.request'), ['email' => $user->email]);

        $response->assertStatus(429);
    }

    public function test_the_reset_link_rate_limit_is_scoped_per_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.request'), ['email' => $user->email]);
        }

        $response = $this->post(route('password.request'), ['email' => $otherUser->email]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login', absolute: false));

            return true;
        });
    }

    public function test_resetting_a_password_stamps_email_verified_at_if_not_already_set(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->post(route('password.request'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $this->assertNotNull($user->fresh()->email_verified_at);

            return true;
        });
    }

    public function test_a_staff_account_must_use_a_strong_password_even_outside_production(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        $this->post(route('password.request'), ['email' => $admin->email]);

        Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $admin->email,
                // Weak: no uppercase, no symbol, no digit.
                'password' => 'weakpassword',
                'password_confirmation' => 'weakpassword',
            ]);

            $response->assertSessionHasErrors('password');

            return true;
        });
    }

    public function test_a_staff_account_can_reset_with_a_strong_password(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        $this->post(route('password.request'), ['email' => $admin->email]);

        Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $admin->email,
                'password' => 'Str0ng!Passw0rd#2026',
                'password_confirmation' => 'Str0ng!Passw0rd#2026',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });
    }

    public function test_a_customer_can_still_use_a_simple_password_outside_production(): void
    {
        Notification::fake();

        $customer = User::factory()->create();

        $this->post(route('password.request'), ['email' => $customer->email]);

        Notification::assertSentTo($customer, ResetPassword::class, function ($notification) use ($customer) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $customer->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });
    }
}
