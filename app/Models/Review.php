<?php

/**
 * A customer's rating and comments on a product they purchased.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUlid;
use App\Concerns\LogsAdminActivity;
use App\Enums\ReviewStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * `order_item_id` is proof of purchase — unique (among non-deleted rows),
 * so a customer can only ever have one active review per purchased line
 * item. The FK alone doesn't prove the purchase actually completed (a
 * `pending`/`cancelled` order's order_item still exists as a row);
 * `SubmitReview` is what additionally checks the parent order's status,
 * never this model on its own. `order_item_id` is nulled out by
 * `DeleteReview` alongside the soft delete — same "free the unique value
 * for reuse" rule as `Product`/`ProductVariant`'s slug/SKU mutation, just
 * applied to a nullable FK instead of a string column; the full attribute
 * history (including the original `order_item_id`) is still preserved via
 * activity log. Only the author may change `rating`/`title`/`body` (via
 * EditReview, which always resets `status` back to `pending`) —
 * `ModerateReview` may only ever change `status`, never the review's content.
 *
 * @property int $id
 * @property string $ulid
 * @property int $product_id
 * @property int $user_id
 * @property int|null $order_item_id
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property ReviewStatus $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_id', 'user_id', 'order_item_id', 'rating', 'title', 'body', 'status'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, HasUlid, LogsAdminActivity, SoftDeletes;

    /**
     * The allowed star-rating bounds — enforced by SubmitReview/EditReview
     * before a rating ever reaches this model.
     */
    public const MIN_RATING = 1;

    public const MAX_RATING = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
        ];
    }

    /**
     * The product being reviewed.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The customer who wrote this review.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The purchased line item this review is proof-of-purchase against.
     *
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
