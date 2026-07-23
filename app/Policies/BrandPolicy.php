<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::StoreKeeper->value]);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    public function restore(User $user, Brand $brand): bool
    {
        return $this->delete($user, $brand);
    }

    public function forceDelete(User $user, Brand $brand): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
