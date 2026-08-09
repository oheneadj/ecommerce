<?php

/**
 * Tier 3 (data integrity) — WARNING: every review links to a genuinely
 * verified purchase.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

use App\Enums\OrderStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A foreign key on `order_item_id` only proves the row exists, not that it
 * represents a completed purchase by that reviewer (technical-design
 * §4g). `SubmitReview` requires the parent order's status to be one of
 * Paid/Processing/Shipped/Delivered and the reviewer to be the order's own
 * customer — this re-checks that same rule against every existing review.
 */
class NoReviewsWithoutVerifiedPurchase implements IntegrityCheck
{
    private const VERIFIED_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Processing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
    ];

    public function name(): string
    {
        return 'No reviews without a verified purchase';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function remediationHint(): string
    {
        return 'This review is not linked to a completed purchase by its own author — investigate whether SubmitReview was bypassed.';
    }

    public function run(): IntegrityCheckOutcome
    {
        $validStatuses = array_map(fn (OrderStatus $status) => $status->value, self::VERIFIED_STATUSES);

        $ids = DB::table('reviews')
            ->join('order_items', 'order_items.id', '=', 'reviews.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where(function (Builder $query) use ($validStatuses) {
                $query->whereNotIn('orders.status', $validStatuses)
                    ->orWhereColumn('orders.user_id', '!=', 'reviews.user_id');
            })
            ->pluck('reviews.id')
            ->all();

        return $ids === [] ? IntegrityCheckOutcome::clean() : IntegrityCheckOutcome::violations($ids);
    }
}
