<?php

/**
 * Submits a customer review against a purchased line item.
 */

declare(strict_types=1);

namespace App\Actions\Review;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\DuplicateReviewException;
use App\Exceptions\InvalidReviewRatingException;
use App\Exceptions\ReviewRequiresVerifiedPurchaseException;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Verified purchase" is not just the `order_item_id` foreign key existing
 * — that row exists the moment an order is created, before payment ever
 * succeeds. A review is only allowed once the parent order has actually
 * progressed past payment (paid/processing/shipped/delivered); a
 * still-pending or cancelled order's line item is not a verified purchase.
 * `reviews.order_item_id` is unique — one review per purchased line item,
 * enforced here before hitting the DB constraint so the caller gets a
 * clear domain exception rather than a raw integrity-violation error.
 *
 * @throws ReviewRequiresVerifiedPurchaseException
 * @throws DuplicateReviewException
 * @throws InvalidReviewRatingException
 */
class SubmitReview
{
    use AsAction;

    private const VERIFIED_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Processing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
    ];

    public function handle(User $user, OrderItem $orderItem, int $rating, string $body, ?string $title = null): Review
    {
        $orderItem->loadMissing('order');

        if ($orderItem->order->user_id !== $user->id || ! in_array($orderItem->order->status, self::VERIFIED_STATUSES, true)) {
            throw new ReviewRequiresVerifiedPurchaseException;
        }

        if ($rating < Review::MIN_RATING || $rating > Review::MAX_RATING) {
            throw new InvalidReviewRatingException;
        }

        if (Review::query()->where('order_item_id', $orderItem->id)->exists()) {
            throw new DuplicateReviewException;
        }

        return Review::query()->create([
            'product_id' => $orderItem->productVariant->product_id,
            'user_id' => $user->id,
            'order_item_id' => $orderItem->id,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => ReviewStatus::Pending,
        ]);
    }
}
