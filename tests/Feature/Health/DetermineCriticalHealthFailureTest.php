<?php

/**
 * Covers the shared "is anything CRITICAL currently failing?" check used by
 * both the System Health page and the persistent admin banner
 * (docs/TASK-system-health-checks.md Step 5.3).
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Actions\Health\DetermineCriticalHealthFailure;
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

class DetermineCriticalHealthFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_is_critical_when_no_super_admin_exists(): void
    {
        $this->assertTrue(DetermineCriticalHealthFailure::run());
    }

    public function test_it_is_not_critical_once_every_required_attestation_passes_and_no_check_is_failing(): void
    {
        // Registered Tier 1/2 checks include several tied to real
        // infrastructure state (APP_ENV, config caching, disk space) that
        // a test process cannot safely fake without side effects — this
        // isolates the assertion to this Action's own logic (integrity
        // results + attestations) by standing in an empty check registry.
        app()->instance(HealthManager::class, new class extends HealthManager
        {
            public function registeredChecks(): Collection
            {
                return collect();
            }
        });
        Facade::clearResolvedInstance(HealthManager::class);

        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        foreach (array_keys(HealthAttestation::REQUIRED) as $key) {
            HealthAttestation::factory()->create(['key' => $key, 'confirmed_by' => $user->id, 'confirmed_at' => now()]);
        }

        $this->assertFalse(DetermineCriticalHealthFailure::run());
    }

    public function test_the_result_is_cached_for_subsequent_calls(): void
    {
        DetermineCriticalHealthFailure::run();

        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        // The cached (stale) result still reports critical even though a
        // Super Admin now exists — proves the 60s cache is actually in effect.
        $this->assertTrue(DetermineCriticalHealthFailure::run());
    }
}
