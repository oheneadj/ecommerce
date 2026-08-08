<?php

/**
 * Covers the admin bar partial — icons only, no emojis (icons render via
 * the shared x-app-icon component, since this partial deliberately avoids
 * Tailwind classes for cross-CSS-pipeline portability).
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_bar_renders_without_error_for_an_admin(): void
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);
        $this->actingAs($user);

        $html = view('partials.admin-bar')->render();

        $this->assertStringContainsString('Admin Dashboard', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('🌐', $html);
        $this->assertStringNotContainsString('🛠️', $html);
        $this->assertStringNotContainsString('🕓', $html);
        $this->assertStringNotContainsString('⚙️', $html);
        $this->assertStringNotContainsString('✅', $html);
    }

    public function test_the_admin_bar_is_invisible_to_a_customer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $html = view('partials.admin-bar')->render();

        $this->assertSame('', trim($html));
    }
}
