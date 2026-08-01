<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LoginWithGoogle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
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
}
