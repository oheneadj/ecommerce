<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

/**
 * Authorization rules for Product: catalog staff can view/manage products,
 * but only Admins/Super Admins can delete or restore them.
 */
class ProductPolicy
{
    /**
     * Any catalog-facing role can view the product list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::StoreKeeper->value]);
    }

    /**
     * Same rule as viewAny.
     */
    public function view(User $user, Product $product): bool
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
    public function update(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Admins/Super Admins only.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    /**
     * Same rule as delete.
     */
    public function restore(User $user, Product $product): bool
    {
        return $this->delete($user, $product);
    }

    /**
     * Super Admin only.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
