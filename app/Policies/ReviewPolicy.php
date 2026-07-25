<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Review;
use App\Models\User;

/**
 * Governs both the Filament moderation panel (Admin/Super Admin only —
 * Store Keeper's role never extends to reviews) and, for reuse when a
 * storefront is built, the customer-facing authorization matching
 * EditReview/DeleteReview's own rules: only the author may update, the
 * author or staff may delete.
 */
class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);
    }

    public function view(User $user, Review $review): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $this->viewAny($user);
    }

    public function restore(User $user, Review $review): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, Review $review): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
