<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\User;

/**
 * Authorization rules for Attribute: catalog staff can view attributes,
 * but only Admins/Super Admins can create, edit, or delete them.
 */
class AttributePolicy
{
    /**
     * Any catalog-facing role can view the attribute list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::StoreKeeper->value]);
    }

    /**
     * Same rule as viewAny.
     */
    public function view(User $user, Attribute $attribute): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Admins/Super Admins only.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    /**
     * Same rule as create.
     */
    public function update(User $user, Attribute $attribute): bool
    {
        return $this->create($user);
    }

    /**
     * Same rule as create.
     */
    public function delete(User $user, Attribute $attribute): bool
    {
        return $this->create($user);
    }

    /**
     * Same rule as delete.
     */
    public function restore(User $user, Attribute $attribute): bool
    {
        return $this->delete($user, $attribute);
    }

    /**
     * Super Admin only.
     */
    public function forceDelete(User $user, Attribute $attribute): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
