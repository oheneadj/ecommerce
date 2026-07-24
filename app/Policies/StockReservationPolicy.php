<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockReservation;
use App\Models\User;

/**
 * Reservations are entirely system-managed (created by ReserveStockForOrder,
 * transitioned by ReleaseExpiredReservations / AdjustStockWithReservationCheck /
 * payment-webhook handling) — staff can view them but never create, edit, or delete one by hand.
 */
class StockReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::StoreKeeper->value]);
    }

    public function view(User $user, StockReservation $stockReservation): bool
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
    public function update(User $user, StockReservation $stockReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockReservation $stockReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StockReservation $stockReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StockReservation $stockReservation): bool
    {
        return false;
    }
}
