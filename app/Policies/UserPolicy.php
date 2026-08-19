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
 * inventory, never orders, payments, or customers (BRD role table). A
 * customer's own data (name/email/phone) is never created, edited, or
 * deleted from the admin panel — they self-register via phone/OTP or
 * Google. Account-state actions that don't touch that data — sending a
 * message, disabling/enabling — are a different category, same as
 * `sendCommunication` below.
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

    /**
     * Sending an email/SMS to a customer isn't a mutation of their own
     * record, but it's a real capability that shouldn't be reachable by
     * anyone outside the same Admin/Super Admin scope as everything else
     * on this resource.
     */
    public function sendCommunication(User $user, User $customer): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Disabling/re-enabling a customer account — same scope as everything
     * else here. No self-disable risk to guard against: the Customers
     * resource is already scoped to non-staff accounts only
     * (`CustomerResource::getEloquentQuery()`), so an acting Admin/Super
     * Admin can never appear as a `$customer` here in the first place.
     */
    public function setDisabledState(User $user, User $customer): bool
    {
        return $this->viewAny($user);
    }
}
