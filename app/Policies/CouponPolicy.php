<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\User;

/**
 * Coupons are a marketing/pricing feature — Admin/Super Admin only, same
 * scope as Order (Store Keeper is inventory-only per BRD role table).
 */
class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, Coupon $coupon): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
