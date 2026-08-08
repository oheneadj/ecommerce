<?php

/**
 * Enforces that a user can have only one default address.
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\Address;

/**
 * Runs on every create/update regardless of entry point, so the invariant
 * holds no matter how an address gets marked default. A guest checkout
 * address (`user_id` null) is never scoped against anything — "default"
 * only means something for a saved account address.
 */
class AddressObserver
{
    public function saving(Address $address): void
    {
        if (! $address->is_default || $address->user_id === null) {
            return;
        }

        Address::query()
            ->where('user_id', $address->user_id)
            ->when($address->exists, fn ($query) => $query->where('id', '!=', $address->id))
            ->update(['is_default' => false]);
    }
}
