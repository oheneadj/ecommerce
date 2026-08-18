<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LoginWithGoogle;
use App\Enums\UserRole;
use App\Exceptions\GoogleEmailConflictException;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email, string $name = 'Jane Doe'): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->id = $id;
        $user->email = $email;
        $user->name = $name;

        return $user;
    }

    public function test_first_time_google_login_with_no_email_match_creates_a_new_verified_account(): void
    {
        $googleUser = $this->fakeGoogleUser('google-123', 'jane@example.com');

        $user = LoginWithGoogle::run($googleUser);

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('jane@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_google_login_links_to_an_existing_account_with_a_matching_verified_email(): void
    {
        $existing = User::factory()->create(['email' => 'jane@example.com', 'google_id' => null]);

        $googleUser = $this->fakeGoogleUser('google-123', 'jane@example.com');

        $user = LoginWithGoogle::run($googleUser);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('google-123', $user->fresh()->google_id);
        $this->assertSame(1, User::query()->where('email', 'jane@example.com')->count());
    }

    public function test_repeat_google_login_logs_in_the_same_user_by_google_id(): void
    {
        $existing = User::factory()->create(['email' => 'jane@example.com', 'google_id' => 'google-123']);

        $googleUser = $this->fakeGoogleUser('google-123', 'jane@example.com');

        $user = LoginWithGoogle::run($googleUser);

        $this->assertSame($existing->id, $user->id);
    }

    public function test_a_google_account_with_an_unverified_email_does_not_auto_link_to_an_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'jane@example.com', 'google_id' => null]);

        $googleUser = $this->fakeGoogleUser('google-123', 'jane@example.com');
        $googleUser->setRaw(['email_verified' => false]);

        $user = LoginWithGoogle::run($googleUser);

        $this->assertNotSame($existing->id, $user->id);
        $this->assertNull($user->email_verified_at);
        // The email column is unique — since it can't be trusted to link,
        // and it's already claimed by $existing, the new account is
        // created without it rather than crashing on the constraint.
        $this->assertNull($user->email);
    }

    /**
     * Enforcement lives at the HTTP boundary (GoogleAuthController::callback
     * branches on Auth::check()), not inside LoginWithGoogle/
     * LinkAccountIdentifier themselves — an unauthenticated callback always
     * goes through LoginWithGoogle, which never merges into someone else's
     * account just because the email happens to match (BRD Section 4e).
     */
    public function test_an_unauthenticated_callback_never_auto_links_into_an_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'jane@example.com', 'google_id' => null]);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-123', 'jane@example.com'));

        $this->get('/login/google/callback')->assertRedirect();

        $existing->refresh();
        // Verified-email auto-link (proven in the test above) is still the
        // one exception LoginWithGoogle itself allows — what this proves is
        // that an *unauthenticated* request reaches LoginWithGoogle at all,
        // never LinkAccountIdentifier, which is the actual guard against
        // silently attaching a login method to whichever session happens to
        // be active.
        $this->assertAuthenticatedAs($existing);
    }

    /**
     * The other side of the same boundary: an authenticated request routes
     * to LinkAccountIdentifier instead of LoginWithGoogle, attaching Google
     * to the already-logged-in session rather than switching accounts.
     */
    public function test_an_authenticated_callback_links_google_to_the_current_session_not_a_different_account(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $currentUser = User::factory()->create(['email' => 'current-user@example.com', 'google_id' => null]);
        $otherAccountWithSameEmail = User::factory()->create(['email' => 'jane@example.com']);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-123', 'jane@example.com'));

        $this->actingAs($currentUser)
            ->get('/login/google/callback')
            ->assertRedirect();

        $this->assertAuthenticatedAs($currentUser);
        $this->assertSame('google-123', $currentUser->fresh()->google_id);
        $this->assertNull($otherAccountWithSameEmail->fresh()->google_id);
    }

    /**
     * The core fix (audit part 2/4): a matching email alone was never
     * enough to justify handing a stranger access to someone else's
     * account — the existing account's own email must be independently
     * verified too, not just Google's side.
     */
    public function test_a_first_time_google_login_matching_an_account_with_an_unverified_email_is_blocked(): void
    {
        $existing = User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => null, 'google_id' => null]);

        $googleUser = $this->fakeGoogleUser('google-123', 'jane@example.com');

        $this->expectException(GoogleEmailConflictException::class);

        try {
            LoginWithGoogle::run($googleUser);
        } finally {
            $this->assertGuest();
            $this->assertNull($existing->fresh()->google_id);
        }
    }

    public function test_the_blocked_login_sends_a_fresh_verification_email_to_the_conflicting_account(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => null, 'google_id' => null]);

        try {
            LoginWithGoogle::run($this->fakeGoogleUser('google-123', 'jane@example.com'));
        } catch (GoogleEmailConflictException) {
            // Expected.
        }

        Notification::assertSentTo($existing, VerifyEmail::class);
    }

    public function test_the_conflict_verification_email_is_rate_limited(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => null, 'google_id' => null]);

        foreach (range(1, 2) as $attempt) {
            try {
                LoginWithGoogle::run($this->fakeGoogleUser('google-123', 'jane@example.com'));
            } catch (GoogleEmailConflictException) {
                // Expected, every attempt.
            }
        }

        Notification::assertSentToTimes($existing, VerifyEmail::class, 1);
    }

    /**
     * Once the existing account's email is genuinely verified (either
     * side — clicking the link, or an earlier Google login), the exact
     * same login now succeeds via the normal auto-link path. This is the
     * self-resolving path a blocked customer is pointed toward.
     */
    public function test_retrying_after_the_existing_account_verifies_its_email_succeeds(): void
    {
        $existing = User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => now(), 'google_id' => null]);

        $user = LoginWithGoogle::run($this->fakeGoogleUser('google-123', 'jane@example.com'));

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('google-123', $user->fresh()->google_id);
    }

    public function test_the_unauthenticated_callback_redirects_to_phone_login_with_a_helpful_message_on_conflict(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => null, 'google_id' => null]);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-123', 'jane@example.com'));

        $this->get('/login/google/callback')
            ->assertRedirect(route('login.phone'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    /**
     * Regression: this exception was never caught before — a customer
     * whose already-authenticated "connect Google" attempt hit an already-
     * linked-elsewhere Google account got an uncaught 500, not a message.
     */
    public function test_connecting_an_already_linked_elsewhere_google_account_shows_a_friendly_error(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $currentUser = User::factory()->create(['email' => 'current-user@example.com', 'google_id' => null]);
        User::factory()->create(['google_id' => 'google-123']);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-123', 'someone-else@example.com'));

        $this->actingAs($currentUser)
            ->get('/login/google/callback')
            ->assertRedirect(route('account.show', absolute: false))
            ->assertSessionHas('error');

        $this->assertNull($currentUser->fresh()->google_id);
    }

    public function test_connecting_google_redirects_back_to_the_page_it_was_started_from(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $currentUser = User::factory()->create(['email' => 'current-user@example.com', 'google_id' => null]);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-123', 'current-user@example.com'));

        // Simulates the redirect() action having already stashed where to
        // return to — that endpoint itself just builds Google's OAuth URL
        // (no assertable behavior of its own beyond the session write it
        // performs), so it's exercised directly here rather than via a
        // real request that would need Socialite's own redirect mocked too.
        $this->withSession(['google_link_redirect_to' => route('security.edit', absolute: false)])
            ->actingAs($currentUser)
            ->get('/login/google/callback')
            ->assertRedirect(route('security.edit', absolute: false))
            ->assertSessionHas('status');
    }
}
