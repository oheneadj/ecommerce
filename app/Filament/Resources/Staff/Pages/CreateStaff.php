<?php

/**
 * Invite-a-staff-member create page.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Pages;

use App\Actions\Staff\InviteStaffMember;
use App\Enums\UserRole;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    /**
     * Delegates to `InviteStaffMember` rather than the default "just save
     * the form data" behavior — creating a staff account is a real
     * business operation (placeholder password, role assignment, sending
     * the invite), not a plain Eloquent insert.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return InviteStaffMember::run(
            $data['name'],
            $data['email'],
            $data['phone'],
            UserRole::from($data['role']),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
