<?php

/**
 * Covers the `app:create-super-admin` command — the real, safe way to
 * create the first Super Admin for a fresh deployment (Epic E13.3),
 * as opposed to UserSeeder's fake demo accounts.
 */

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin_with_the_role_assigned(): void
    {
        // assertExitCode() alone only queues the assertion — the command
        // actually executes lazily via __destruct(), whose timing relative
        // to the next line isn't guaranteed. ->run() forces it to execute
        // before any assertion that depends on its side effects.
        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('Password', 'a-strong-password-123')
            ->expectsQuestion('Confirm password', 'a-strong-password-123')
            ->assertExitCode(0)
            ->run();

        $user = User::query()->where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(UserRole::SuperAdmin->value));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_it_fails_when_the_password_confirmation_does_not_match(): void
    {
        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('Password', 'a-strong-password-123')
            ->expectsQuestion('Confirm password', 'a-different-password')
            ->assertExitCode(1)
            ->run();

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_it_fails_when_the_email_is_already_taken(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('Password', 'a-strong-password-123')
            ->expectsQuestion('Confirm password', 'a-strong-password-123')
            ->assertExitCode(1)
            ->run();
    }
}
