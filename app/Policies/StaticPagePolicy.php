<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StaticPage;
use App\Models\User;

/**
 * Content pages are marketing/CMS configuration — Admin/Super Admin only,
 * same scope as ShippingMethod (Store Keeper's role never extends to
 * anything content/marketing-facing per the BRD role table).
 */
class StaticPagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    public function view(User $user, StaticPage $staticPage): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StaticPage $staticPage): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, StaticPage $staticPage): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, StaticPage $staticPage): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, StaticPage $staticPage): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
