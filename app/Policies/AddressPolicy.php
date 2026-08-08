<?php

/**
 * Authorization for a customer's saved address book.
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

/**
 * No admin-panel use case (addresses are a pure customer feature, same as
 * WishlistItem) — this exists for the customer-facing address book.
 * Ownership only: a guest checkout address (`user_id` null) is never
 * editable/deletable through here at all.
 */
class AddressPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function delete(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }
}
