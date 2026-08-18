<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Canned recipient segments for the customer-broadcast-notifications admin
 * page. Deliberately a small fixed set (no configurable day-count, no
 * arbitrary filter builder) — the ones that actually come up for "who
 * should get this message", not a general-purpose segmentation tool.
 */
enum CustomerSegment: string
{
    case All = 'all';
    case HasOrdered = 'has_ordered';
    case NeverOrdered = 'never_ordered';
    case JoinedRecently = 'joined_recently';

    /**
     * Human-readable label for the admin form's segment picker.
     */
    public function label(): string
    {
        return match ($this) {
            self::All => 'All customers',
            self::HasOrdered => 'Has placed at least one order',
            self::NeverOrdered => 'Has never placed an order',
            self::JoinedRecently => 'Joined in the last 30 days',
        };
    }

    /**
     * Narrows an already-customers-only query (see User::scopeCustomers())
     * down to this segment.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::All => $query,
            self::HasOrdered => $query->whereHas('orders'),
            self::NeverOrdered => $query->whereDoesntHave('orders'),
            self::JoinedRecently => $query->where('created_at', '>=', Carbon::now()->subDays(30)),
        };
    }

    /**
     * Whether a single visitor falls into this segment — used by
     * storefront announcement targeting, where there's one specific
     * viewer to check rather than a list to filter. A guest (`$user` is
     * null) only ever matches `All` — every other segment depends on
     * order history/account age a guest doesn't have.
     */
    public function matches(?User $user): bool
    {
        if ($this === self::All) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $this->apply(User::query()->customers()->whereKey($user->id))->exists();
    }
}
