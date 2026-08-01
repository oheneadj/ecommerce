<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * The activity log is Super Admin only (BRD E11.4 — narrower than every
 * other staff-facing resource, which are Admin+Super Admin) and strictly
 * read-only: entries are populated automatically by LogsAdminActivity,
 * never created/edited/deleted by hand.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function restore(User $user, Activity $activity): bool
    {
        return false;
    }

    public function forceDelete(User $user, Activity $activity): bool
    {
        return false;
    }
}
