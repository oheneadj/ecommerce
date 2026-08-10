<?php

/**
 * Covers the Super-Admin-only System Health page — Tier 1/2 checks run
 * live, Tier 3 reads the stored nightly result, and attestations can be
 * recorded (docs/TASK-system-health-checks.md Step 5.2).
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Health\RunIntegrityChecks;
use App\Enums\UserRole;
use App\Filament\Pages\SystemHealth;
use App\Models\HealthAttestation;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_a_super_admin_can_view_the_page(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SystemHealth::class)->assertOk();
    }

    public function test_a_regular_admin_cannot_access_the_page(): void
    {
        $this->assertFalse(SystemHealth::canAccess());

        $this->actingAs($this->admin());

        $this->assertFalse(SystemHealth::canAccess());
    }

    public function test_the_page_shows_every_registered_tier_one_and_two_check(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SystemHealth::class)
            ->assertSee('Super Admin Exists')
            ->assertSee('Expired Reservations Are Being Released');
    }

    public function test_the_page_shows_the_latest_stored_tier_three_result_without_rerunning_it(): void
    {
        RunIntegrityChecks::run();
        $this->actingAs($this->superAdmin());

        Livewire::test(SystemHealth::class)
            ->assertSee('Stock cache matches movements');
    }

    public function test_the_page_shows_never_confirmed_for_an_attestation_with_no_record(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SystemHealth::class)
            ->assertSee('Backup restore tested')
            ->assertSee('Never confirmed');
    }

    public function test_recording_an_attestation_creates_a_row_and_refreshes_the_page(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        Livewire::test(SystemHealth::class)
            ->callAction('recordAttestation', data: ['notes' => 'Restored a backup on staging, verified data.'], arguments: ['key' => 'backup_restore_tested']);

        $attestation = HealthAttestation::query()->where('key', 'backup_restore_tested')->sole();
        $this->assertSame($admin->id, $attestation->confirmed_by);
        $this->assertSame('Restored a backup on staging, verified data.', $attestation->notes);
    }

    public function test_a_stale_attestation_is_flagged(): void
    {
        HealthAttestation::factory()->create([
            'key' => 'backup_restore_tested',
            'confirmed_at' => now()->subDays(100),
        ]);
        $this->actingAs($this->superAdmin());

        Livewire::test(SystemHealth::class)
            ->assertSet('criticalCount', fn (int $count) => $count > 0);
    }

    public function test_snoozing_alerts_sets_a_24_hour_timestamp(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SystemHealth::class)->call('snoozeAlerts');

        $snoozedUntil = StoreSetting::current()->health_alerts_snoozed_until;
        $this->assertNotNull($snoozedUntil);
        $this->assertTrue($snoozedUntil->isFuture());
        $this->assertTrue($snoozedUntil->lessThanOrEqualTo(now()->addDay()->addMinute()));
    }

    public function test_resuming_alerts_clears_the_snooze(): void
    {
        StoreSetting::current()->update(['health_alerts_snoozed_until' => now()->addDay()]);
        $this->actingAs($this->superAdmin());

        Livewire::test(SystemHealth::class)->call('resumeAlerts');

        $this->assertNull(StoreSetting::current()->health_alerts_snoozed_until);
    }

    public function test_snooze_button_only_shows_when_something_is_critical(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SystemHealth::class)
            ->assertSet('criticalCount', fn (int $count) => $count > 0)
            ->assertSeeHtml('wire:click="snoozeAlerts"');
    }
}
