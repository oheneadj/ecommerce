<?php

/**
 * Edits a review's own content — only its author may do this.
 */

declare(strict_types=1);

namespace App\Actions\Review;

use App\Enums\ReviewStatus;
use App\Exceptions\InvalidReviewRatingException;
use App\Exceptions\ReviewOwnershipException;
use App\Models\Review;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Always resets `status` back to `pending`, regardless of whatever it was
 * before — an already-approved review can't be quietly edited into
 * something different while keeping its public "approved" badge; it must
 * clear moderation again.
 *
 * @throws ReviewOwnershipException when the acting user didn't write this review
 * @throws InvalidReviewRatingException
 */
class EditReview
{
    use AsAction;

    public function handle(User $user, Review $review, int $rating, string $body, ?string $title = null): Review
    {
        if ($review->user_id !== $user->id) {
            throw new ReviewOwnershipException;
        }

        if ($rating < Review::MIN_RATING || $rating > Review::MAX_RATING) {
            throw new InvalidReviewRatingException;
        }

        $review->update([
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => ReviewStatus::Pending,
        ]);

        return $review;
    }
}
