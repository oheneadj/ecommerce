<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ShippingMethod;
use App\Models\User;

/**
 * Shipping methods are checkout/pricing configuration — Admin/Super Admin
 * only, same scope as Coupon (Store Keeper's role is inventory-only per
 * the BRD role table, not fulfillment configuration).
 */
class ShippingMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    public function view(User $user, ShippingMethod $shippingMethod): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ShippingMethod $shippingMethod): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ShippingMethod $shippingMethod): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, ShippingMethod $shippingMethod): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
