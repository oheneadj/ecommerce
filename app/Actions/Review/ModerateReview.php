<?php

/**
 * Approves or rejects a review — staff only, never touches its content.
 */

declare(strict_types=1);

namespace App\Actions\Review;

use App\Enums\ReviewStatus;
use App\Models\Review;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * May only ever change `status` — never `rating`/`title`/`body`. Only the
 * review's own author can change its content, via EditReview, which itself
 * resets status back to pending. Moderation and authorship are
 * deliberately kept as two separate, non-overlapping write paths.
 */
class ModerateReview
{
    use AsAction;

    public function handle(Review $review, ReviewStatus $status): Review
    {
        $review->update(['status' => $status]);

        return $review;
    }
}
