<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Refund;
use App\Models\User;

/**
 * Refunds are Admin/Super Admin only, matching Payment's scope — they are
 * entirely system-managed (created only by ProcessRefund via the Payments
 * table's "Issue refund" action), never created/edited/deleted by hand here.
 */
class RefundPolicy
{
    /**
     * Admins/Super Admins only.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    /**
     * Same rule as viewAny.
     */
    public function view(User $user, Refund $refund): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Refund $refund): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Refund $refund): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Refund $refund): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Refund $refund): bool
    {
        return false;
    }
}
