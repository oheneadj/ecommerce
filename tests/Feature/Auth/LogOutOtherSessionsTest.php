<?php

/**
 * Covers App\Actions\Auth\LogOutOtherSessions — revoking every other
 * active session for a user after a security-relevant account change
 * (password change, 2FA enable/disable, passkey removal).
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LogOutOtherSessions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LogOutOtherSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'payload' => '',
            'last_activity' => time(),
        ]);
    }

    public function test_it_deletes_every_other_session_for_the_user(): void
    {
        $user = User::factory()->create();
        $this->insertSession('current-session', $user->id);
        $this->insertSession('other-session-1', $user->id);
        $this->insertSession('other-session-2', $user->id);

        LogOutOtherSessions::run($user, 'current-session');

        $this->assertDatabaseHas('sessions', ['id' => 'current-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-2']);
    }

    public function test_it_never_touches_another_users_sessions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->insertSession('current-session', $user->id);
        $this->insertSession('other-users-session', $otherUser->id);

        LogOutOtherSessions::run($user, 'current-session');

        $this->assertDatabaseHas('sessions', ['id' => 'other-users-session']);
    }
}
