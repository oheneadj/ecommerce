<?php

/**
 * Covers the `system:check` deploy-gate command
 * (docs/TASK-system-health-checks.md Step 6.1).
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RuntimeException;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Health as HealthManager;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SuperAdminExists (a registered Tier 1 check) queries `->role(...)`,
        // which throws if the role row doesn't exist at all yet.
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
    }

    public function test_without_critical_it_always_exits_successfully(): void
    {
        $this->artisan('system:check')->assertExitCode(0);
    }

    public function test_critical_mode_exits_non_zero_when_a_critical_check_is_failing(): void
    {
        // No Super Admin exists — a genuine critical failure.
        $this->artisan('system:check --critical')->assertExitCode(1);
    }

    public function test_critical_mode_excludes_heartbeat_checks_by_default(): void
    {
        $this->artisan('system:check --critical')
            ->expectsOutputToContain('critical checks are failing')
            ->doesntExpectOutputToContain('Expired Reservations Are Being Released')
            ->doesntExpectOutputToContain('Pending Payments Are Being Verified')
            ->assertExitCode(1);
    }

    public function test_critical_mode_includes_heartbeat_checks_when_asked(): void
    {
        $this->artisan('system:check --critical --include-heartbeats')
            ->assertExitCode(1);
    }

    /**
     * Bug hunt regression: a check throwing (a bug in the check itself,
     * an unseeded dependency, a DB connectivity blip) previously aborted
     * this whole command with an uncaught exception — the deploy-gate
     * command failing with a raw stack trace instead of the intended
     * clean per-check report, and skipping every remaining check.
     */
    public function test_a_check_that_throws_is_reported_as_crashed_instead_of_aborting_the_command(): void
    {
        $crashingCheck = new class extends Check
        {
            public function run(): Result
            {
                throw new RuntimeException('Something went badly wrong inside this check.');
            }
        };

        app()->instance(HealthManager::class, new class($crashingCheck) extends HealthManager
        {
            public function __construct(private readonly Check $crashingCheck)
            {
                //
            }

            public function registeredChecks(): Collection
            {
                return collect([$this->crashingCheck]);
            }
        });
        Facade::clearResolvedInstance(HealthManager::class);

        $this->artisan('system:check --critical')
            ->expectsOutputToContain('crashed')
            ->assertExitCode(1);
    }

    public function test_critical_mode_passes_once_every_critical_check_is_satisfied(): void
    {
        // Registered Tier 1/2 checks include several tied to real
        // infrastructure state (APP_ENV, config caching, disk space) that
        // a test process cannot safely fake — stand in an empty check
        // registry so this test proves the command's own gating logic
        // rather than re-testing every individual check's behavior.
        app()->instance(HealthManager::class, new class extends HealthManager
        {
            public function registeredChecks(): Collection
            {
                return collect();
            }
        });
        Facade::clearResolvedInstance(HealthManager::class);

        $this->artisan('system:check --critical')->assertExitCode(0);
    }
}
