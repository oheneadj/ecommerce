<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockMovement;
use App\Models\User;

/**
 * Stock movements are an immutable log: any staff role can view/record one,
 * nobody can update, delete, restore, or force-delete an entry — the ledger
 * must never be edited after the fact.
 */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value, UserRole::StoreKeeper->value]);
    }

    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }

    public function restore(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }
}
