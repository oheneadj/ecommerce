<?php

/**
 * Deletes a review — its own author, or an Admin/Super Admin, may do this.
 */

declare(strict_types=1);

namespace App\Actions\Review;

use App\Enums\UserRole;
use App\Exceptions\ReviewOwnershipException;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A soft delete — the row is preserved for audit, just hidden from public
 * display. The author can delete their own review at any time (BRD
 * FR-7.5); an Admin/Super Admin may also remove one as a moderation
 * action, distinguished only by who's acting.
 *
 * `order_item_id` is nulled out before the soft delete (same "free the
 * unique value for reuse" rule as Product/ProductVariant's slug/SKU
 * mutation) — otherwise the still-unique FK would permanently block the
 * customer from ever submitting a new review for that same purchased line
 * item. The original order_item_id remains recoverable via activity log.
 *
 * @throws ReviewOwnershipException when the acting user is neither the
 *                                  author nor staff
 */
class DeleteReview
{
    use AsAction;

    public function handle(User $actor, Review $review): void
    {
        $isAuthor = $review->user_id === $actor->id;
        $isStaff = $actor->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]);

        if (! $isAuthor && ! $isStaff) {
            throw new ReviewOwnershipException;
        }

        DB::transaction(function () use ($review): void {
            $review->update(['order_item_id' => null]);
            $review->delete();
        });
    }
}
