<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\User;

class AttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::StoreKeeper->value]);
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, Attribute $attribute): bool
    {
        return $this->delete($user, $attribute);
    }

    public function forceDelete(User $user, Attribute $attribute): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
