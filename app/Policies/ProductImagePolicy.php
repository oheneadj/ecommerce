<?php

/**
 * Authorization rules for managing product/variant images.
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductImage;
use App\Models\User;

/**
 * Mirrors ProductPolicy — images are just another facet of a product/variant,
 * so whoever can manage the product can manage its images.
 */
class ProductImagePolicy
{
    /**
     * Store Keeper, Admin, and Super Admin can all view product images.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::StoreKeeper->value]);
    }

    /**
     * Same visibility rule as viewAny — no per-record restriction.
     */
    public function view(User $user, ProductImage $productImage): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Same roles as viewAny may upload new images.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Same roles as viewAny may reassign scope/ordering.
     */
    public function update(User $user, ProductImage $productImage): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Deleting an image is restricted to Admin/Super Admin, matching ProductPolicy::delete().
     */
    public function delete(User $user, ProductImage $productImage): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }
}
