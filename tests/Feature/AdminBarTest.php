<?php

/**
 * Covers the admin bar partial — icons only, no emojis (icons render via
 * the shared x-app-icon component, since this partial deliberately avoids
 * Tailwind classes for cross-CSS-pipeline portability) — and the critical
 * health alert item, role-differentiated for Super Admin vs Admin.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\HealthAttestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Spatie\Health\Health as HealthManager;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every render now runs the health checks (including
        // SuperAdminExists), which throws if the role doesn't exist in
        // the DB at all yet — independent of who actually holds it.
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        Cache::flush();
    }

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    public function test_the_admin_bar_renders_without_error_for_an_admin(): void
    {
        $this->actingAs($this->admin());

        $html = view('partials.admin-bar')->render();

        $this->assertStringContainsString('Admin Dashboard', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('🌐', $html);
        $this->assertStringNotContainsString('🛠️', $html);
        $this->assertStringNotContainsString('🕓', $html);
        $this->assertStringNotContainsString('⚙️', $html);
        $this->assertStringNotContainsString('✅', $html);
    }

    public function test_the_admin_bar_stays_below_filaments_notification_toast_layer(): void
    {
        // Filament's notification toasts render at z-50 and its own
        // topbar/sidebar at z-30 — the bar must sit between them (above
        // the panel's nav chrome, below notifications) so a toast is
        // never covered by this bar, regardless of what it was set to
        // before.
        $this->actingAs($this->admin());

        $html = view('partials.admin-bar')->render();

        $this->assertMatchesRegularExpression('/\.wp-admin-bar\s*\{[^}]*z-index:\s*40;/', $html);
    }

    public function test_the_admin_bar_is_invisible_to_a_customer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $html = view('partials.admin-bar')->render();

        $this->assertSame('', trim($html));
    }

    public function test_a_super_admin_sees_a_link_to_system_health_when_critical(): void
    {
        // No Super Admin holds the role yet (the seeded role above has no
        // members) — SuperAdminExists genuinely fails, so this is a real
        // critical failure, not a faked one.
        $this->actingAs($this->superAdmin());

        $html = view('partials.admin-bar')->render();

        $this->assertStringContainsString('Critical issue', $html);
        $this->assertStringContainsString(route('filament.admin.pages.system-health'), $html);
    }

    public function test_an_admin_sees_a_generic_message_with_no_link_when_critical(): void
    {
        $this->actingAs($this->admin());

        $html = view('partials.admin-bar')->render();

        $this->assertStringContainsString('contact your Super Admin', $html);
        $this->assertStringNotContainsString(route('filament.admin.pages.system-health'), $html);
    }

    public function test_no_critical_alert_shows_once_every_critical_check_passes(): void
    {
        // Several registered Tier 1/2 checks are tied to real
        // infrastructure state (APP_ENV, config caching, disk space) that
        // a test process cannot safely fake — this isolates the assertion
        // to the alert item's own on/off logic by standing in an empty
        // check registry, same technique as DetermineCriticalHealthFailureTest.
        app()->instance(HealthManager::class, new class extends HealthManager
        {
            public function registeredChecks(): Collection
            {
                return collect();
            }
        });
        Facade::clearResolvedInstance(HealthManager::class);

        $superAdmin = $this->superAdmin();

        foreach (array_keys(HealthAttestation::REQUIRED) as $key) {
            HealthAttestation::factory()->create(['key' => $key, 'confirmed_by' => $superAdmin->id, 'confirmed_at' => now()]);
        }

        $this->actingAs($superAdmin);

        $html = view('partials.admin-bar')->render();

        $this->assertStringNotContainsString('Critical issue', $html);
    }
}
