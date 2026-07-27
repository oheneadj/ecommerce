<?php

/**
 * Authorization for viewing customer accounts in the admin panel.
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Named UserPolicy (not CustomerPolicy) so Laravel's model↔policy
 * auto-discovery convention actually picks it up — the Customers resource
 * is backed by the User model (scoped to non-staff accounts), not a
 * separate Eloquent model.
 *
 * Customers are Admin/Super Admin only — Store Keeper's role is scoped to
 * inventory, never orders, payments, or customers (BRD role table).
 * Read-only: customer accounts are never created, edited, or deleted from
 * the admin panel — they self-register via phone/OTP or Google.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    public function view(User $user, User $customer): bool
    {
        return $this->viewAny($user);
    }
}
