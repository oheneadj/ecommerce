<?php

/**
 * Covers the admin bar's "Cache" actions (SystemCacheController) —
 * Admin/Super Admin only, since these affect the whole application's
 * runtime state, not just the acting user's own data.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemCacheActionsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_super_admin_can_run_a_cache_action(): void
    {
        $superAdmin = $this->userWithRole(UserRole::SuperAdmin);

        $response = $this->actingAs($superAdmin)->post(route('system.cache.run', ['action' => 'config']));

        $response->assertRedirect();
        $response->assertSessionHas('cache_status', 'Config cache cleared');
    }

    public function test_it_actually_invokes_the_artisan_command(): void
    {
        $superAdmin = $this->userWithRole(UserRole::SuperAdmin);

        Artisan::shouldReceive('call')
            ->once()
            ->with('optimize:clear')
            ->andReturn(0);

        $this->actingAs($superAdmin)->post(route('system.cache.run', ['action' => 'all']));
    }

    public function test_admin_can_run_a_cache_action(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);

        $response = $this->actingAs($admin)->post(route('system.cache.run', ['action' => 'config']));

        $response->assertRedirect();
        $response->assertSessionHas('cache_status', 'Config cache cleared');
    }

    public function test_store_keeper_cannot_run_a_cache_action(): void
    {
        $storeKeeper = $this->userWithRole(UserRole::StoreKeeper);

        $response = $this->actingAs($storeKeeper)->post(route('system.cache.run', ['action' => 'config']));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post(route('system.cache.run', ['action' => 'config']));

        $response->assertRedirect();
        $this->assertGuest();
    }

    public function test_unknown_action_is_rejected(): void
    {
        $superAdmin = $this->userWithRole(UserRole::SuperAdmin);

        $response = $this->actingAs($superAdmin)->post('/system/cache/drop-tables');

        $response->assertNotFound();
    }

    /**
     * Not externally exploitable (Admin/SuperAdmin-only), but a
     * compromised or CSRF-adjacent admin session had no cap on repeated
     * Artisan commands before this.
     */
    public function test_repeated_requests_past_the_rate_limit_are_rejected(): void
    {
        $superAdmin = $this->userWithRole(UserRole::SuperAdmin);
        $this->actingAs($superAdmin);

        for ($i = 0; $i < 20; $i++) {
            $this->post(route('system.cache.run', ['action' => 'config']));
        }

        $response = $this->post(route('system.cache.run', ['action' => 'config']));

        $response->assertStatus(429);
    }
}
