<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;

/**
 * Authorization rules for Brand: catalog staff can view/manage brands,
 * but only Admins/Super Admins can delete or restore them.
 */
class BrandPolicy
{
    /**
     * Any catalog-facing role can view the brand list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::StoreKeeper->value]);
    }

    /**
     * Same rule as viewAny.
     */
    public function view(User $user, Brand $brand): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Same rule as viewAny.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Same rule as viewAny.
     */
    public function update(User $user, Brand $brand): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Admins/Super Admins only.
     */
    public function delete(User $user, Brand $brand): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    /**
     * Same rule as delete.
     */
    public function restore(User $user, Brand $brand): bool
    {
        return $this->delete($user, $brand);
    }

    /**
     * Super Admin only.
     */
    public function forceDelete(User $user, Brand $brand): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
