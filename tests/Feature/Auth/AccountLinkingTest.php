<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LinkAccountIdentifier;
use App\Actions\Auth\SetPassword;
use App\Exceptions\AccountIdentifierAlreadyLinkedException;
use App\Exceptions\GoogleEmailAlreadyTakenException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_password_lets_an_authenticated_user_add_a_password_login(): void
    {
        $user = User::factory()->create(['phone' => '+233201234567', 'password' => null]);

        SetPassword::run($user, 'a-strong-password');

        $this->assertTrue(Hash::check('a-strong-password', $user->fresh()->password));
    }

    public function test_link_account_identifier_attaches_google_to_the_authenticated_users_account(): void
    {
        $user = User::factory()->create(['phone' => '+233201234567', 'email' => null, 'google_id' => null]);

        LinkAccountIdentifier::run($user, 'google-999', 'jane@example.com');

        $user->refresh();
        $this->assertSame('google-999', $user->google_id);
        $this->assertSame('jane@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_link_account_identifier_rejects_a_google_account_already_linked_to_someone_else(): void
    {
        User::factory()->create(['google_id' => 'google-999']);
        $user = User::factory()->create(['phone' => '+233209999999', 'google_id' => null]);

        $this->expectException(AccountIdentifierAlreadyLinkedException::class);

        LinkAccountIdentifier::run($user, 'google-999', 'someone@example.com');
    }

    /**
     * A phone-only user (no email yet) connecting a Google account whose
     * email already belongs to a different existing account used to crash
     * with an uncaught QueryException on the users_email_unique
     * constraint — this must be a clean, named rejection instead, exactly
     * like LinkPhoneToAccount's own already-linked check.
     */
    public function test_link_account_identifier_rejects_a_google_email_already_used_by_another_account(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $user = User::factory()->create(['phone' => '+233209999999', 'email' => null, 'google_id' => null]);

        $this->expectException(GoogleEmailAlreadyTakenException::class);

        LinkAccountIdentifier::run($user, 'google-999', 'existing@example.com');
    }

    public function test_a_rejected_google_email_conflict_never_partially_updates_the_account(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $user = User::factory()->create(['phone' => '+233209999999', 'email' => null, 'google_id' => null]);

        try {
            LinkAccountIdentifier::run($user, 'google-999', 'existing@example.com');
        } catch (GoogleEmailAlreadyTakenException) {
            // expected
        }

        $user->refresh();
        $this->assertNull($user->google_id);
        $this->assertNull($user->email);
    }
}
