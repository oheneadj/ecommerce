<?php

/**
 * Covers the Staff admin resource — Super-Admin-only access, Super Admin
 * accounts never resolving through it, invite-on-create, role changes on
 * edit, resend invite, and disable/enable (single + bulk).
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use App\Notifications\StaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        Role::findOrCreate(UserRole::Admin->value, 'web');
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function staffMember(UserRole $role = UserRole::Admin): User
    {
        $user = User::factory()->create(['phone' => fake()->unique()->numerify('+233#########')]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_cannot_access_the_staff_resource(): void
    {
        $this->actingAs($this->admin());

        $this->assertFalse(StaffResource::canViewAny());
        $this->assertFalse(StaffResource::canCreate());
    }

    public function test_super_admin_can_access_the_staff_resource(): void
    {
        $this->actingAs($this->superAdmin());

        $this->assertTrue(StaffResource::canViewAny());
        $this->assertTrue(StaffResource::canCreate());
    }

    public function test_a_super_admin_account_never_appears_in_the_staff_list(): void
    {
        $this->actingAs($this->superAdmin());

        $otherSuperAdmin = $this->superAdmin();
        $admin = $this->staffMember(UserRole::Admin);

        Livewire::test(ListStaff::class)
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$otherSuperAdmin]);
    }

    public function test_a_super_admin_record_404s_via_a_direct_edit_url(): void
    {
        $this->actingAs($this->superAdmin());

        $otherSuperAdmin = $this->superAdmin();

        $this->get(StaffResource::getUrl('edit', ['record' => $otherSuperAdmin]))
            ->assertNotFound();
    }

    public function test_creating_a_staff_member_invites_them_via_mail_and_sms(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateStaff::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '+233551234567',
                'role' => UserRole::Admin->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue($staff->hasRole(UserRole::Admin->value));
        Notification::assertSentTo($staff, StaffInvited::class);
    }

    /**
     * The phone field only normalized on a client-side blur event —
     * skippable (Enter key, autofill, or as here, a form fill/submit with
     * no blur ever firing at all) — so a local-format number could
     * previously be saved verbatim instead of the canonical E.164 form
     * every other phone input path stores, silently breaking SMS delivery.
     */
    public function test_a_locally_formatted_phone_is_normalized_to_e164_even_without_a_blur_event(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateStaff::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '0551234567',
                'role' => UserRole::Admin->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame('+233551234567', $staff->phone);
    }

    public function test_editing_changes_the_staff_members_role(): void
    {
        $this->actingAs($this->superAdmin());
        $staff = $this->staffMember(UserRole::Admin);

        Livewire::test(EditStaff::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['role' => UserRole::StoreKeeper->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($staff->fresh()->hasRole(UserRole::StoreKeeper->value));
        $this->assertFalse($staff->fresh()->hasRole(UserRole::Admin->value));
    }

    /**
     * syncRoles() writes directly to the model_has_roles pivot table and
     * never fires Eloquent's save/update events, so User's
     * LogsAdminActivity hooks (which listen on those events) never see a
     * role change — this used to leave promoting/demoting a staff member
     * completely unrecorded, unlike every other staff-account mutation.
     */
    public function test_changing_a_staff_members_role_is_recorded_in_the_activity_log(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin);
        $staff = $this->staffMember(UserRole::Admin);

        Livewire::test(EditStaff::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['role' => UserRole::StoreKeeper->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $entry = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $staff->id)
            ->where('description', 'role changed')
            ->sole();

        $this->assertSame($superAdmin->id, $entry->causer_id);
        $this->assertSame(UserRole::Admin->value, $entry->properties['old']['role']);
        $this->assertSame(UserRole::StoreKeeper->value, $entry->properties['attributes']['role']);
    }

    public function test_saving_without_actually_changing_the_role_does_not_log_a_role_change(): void
    {
        $this->actingAs($this->superAdmin());
        $staff = $this->staffMember(UserRole::Admin);

        Livewire::test(EditStaff::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['role' => UserRole::Admin->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Activity::query()->where('description', 'role changed')->count());
    }

    public function test_resend_invite_action_is_only_visible_for_an_unverified_staff_member(): void
    {
        $this->actingAs($this->superAdmin());

        $invited = $this->staffMember();
        $invited->forceFill(['email_verified_at' => null])->save();
        $active = $this->staffMember();

        Livewire::test(ListStaff::class)
            ->assertTableActionVisible('resendInvite', $invited)
            ->assertTableActionHidden('resendInvite', $active);
    }

    public function test_disabling_a_staff_member_via_the_table_action(): void
    {
        $this->actingAs($this->superAdmin());
        $staff = $this->staffMember();

        Livewire::test(ListStaff::class)
            ->callTableAction('disable', $staff)
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($staff->fresh()->disabled_at);
    }

    public function test_enabling_a_staff_member_via_the_table_action(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());
        $staff = $this->staffMember();
        $staff->update(['disabled_at' => now()]);

        Livewire::test(ListStaff::class)
            ->callTableAction('enable', $staff)
            ->assertHasNoTableActionErrors();

        $this->assertNull($staff->fresh()->disabled_at);
    }

    public function test_bulk_disable_disables_every_selected_staff_member(): void
    {
        $this->actingAs($this->superAdmin());
        $staffA = $this->staffMember();
        $staffB = $this->staffMember();

        Livewire::test(ListStaff::class)
            ->callTableBulkAction('bulkDisable', [$staffA, $staffB])
            ->assertHasNoTableBulkActionErrors();

        $this->assertNotNull($staffA->fresh()->disabled_at);
        $this->assertNotNull($staffB->fresh()->disabled_at);
    }

    public function test_bulk_enable_enables_every_selected_staff_member(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin());
        $staffA = $this->staffMember();
        $staffA->update(['disabled_at' => now()]);
        $staffB = $this->staffMember();
        $staffB->update(['disabled_at' => now()]);

        Livewire::test(ListStaff::class)
            ->callTableBulkAction('bulkEnable', [$staffA, $staffB])
            ->assertHasNoTableBulkActionErrors();

        $this->assertNull($staffA->fresh()->disabled_at);
        $this->assertNull($staffB->fresh()->disabled_at);
    }
}
