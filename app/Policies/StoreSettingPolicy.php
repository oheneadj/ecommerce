<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StoreSetting;
use App\Models\User;

/**
 * Store settings affect every business behaviour deployment-wide — Super
 * Admin only, never Admin or Store Keeper.
 */
class StoreSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, StoreSetting $storeSetting): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StoreSetting $storeSetting): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, StoreSetting $storeSetting): bool
    {
        return false;
    }

    public function restore(User $user, StoreSetting $storeSetting): bool
    {
        return false;
    }

    public function forceDelete(User $user, StoreSetting $storeSetting): bool
    {
        return false;
    }
}
