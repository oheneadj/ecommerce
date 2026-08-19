<?php

/**
 * Edit-a-staff-member page.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    /**
     * `role` isn't a real column (`dehydrated(false)` on the form field)
     * — applied here via `syncRoles()` instead of Eloquent's mass
     * assignment, same reason `CreateStaff` delegates role assignment to
     * `InviteStaffMember` rather than the default create behavior.
     *
     * `syncRoles()` writes straight to the `model_has_roles` pivot table —
     * it never fires Eloquent's save/update events, so `User`'s
     * `LogsAdminActivity` hooks (which listen on those events) never see
     * a role change at all, unlike every other staff-account mutation.
     * Logged explicitly here so promoting/demoting a staff member is
     * still a recorded, attributable event.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof User) {
            throw new RuntimeException('StaffResource records are always User models.');
        }

        $role = $data['role'];
        unset($data['role']);

        // The form's rendered options and its own validation rule already
        // restrict this to Admin/Store Keeper, but this handler doesn't
        // trust either alone — Super Admin accounts are CLI-only, never
        // grantable from this panel, so this is the actual guarantee.
        if (! in_array($role, [UserRole::Admin->value, UserRole::StoreKeeper->value], true)) {
            throw new RuntimeException('Staff accounts can only be assigned the Admin or Store Keeper role.');
        }

        $previousRole = $record->roles()->value('name');

        $record->update($data);
        $record->syncRoles([$role]);

        if ($previousRole !== $role) {
            activity('User')
                ->causedBy(Auth::user())
                ->performedOn($record)
                ->withProperties(['old' => ['role' => $previousRole], 'attributes' => ['role' => $role]])
                ->log('role changed');
        }

        return $record;
    }
}
