<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Orders are Admin/Super Admin only — Store Keeper's role is scoped to
 * inventory, never orders or payments (BRD role table). Orders are never
 * created via the admin panel (only CreateOrderFromCart creates one) and
 * never deleted (they're financial/audit records).
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function restore(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }
}
