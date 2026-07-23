<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LinkAccountIdentifier;
use App\Actions\Auth\SetPassword;
use App\Exceptions\AccountIdentifierAlreadyLinkedException;
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
}
