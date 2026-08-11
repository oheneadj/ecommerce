<?php

/**
 * Edit-a-staff-member page.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
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
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof User) {
            throw new RuntimeException('StaffResource records are always User models.');
        }

        $role = $data['role'];
        unset($data['role']);

        $record->update($data);
        $record->syncRoles([$role]);

        return $record;
    }
}
