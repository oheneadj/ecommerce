<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Actions\Review\DeleteReview;
use App\Actions\Review\EditReview;
use App\Actions\Review\ModerateReview;
use App\Actions\Review\SubmitReview;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Exceptions\DuplicateReviewException;
use App\Exceptions\ReviewOwnershipException;
use App\Exceptions\ReviewRequiresVerifiedPurchaseException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function purchasedItem(User $user, OrderStatus $status = OrderStatus::Delivered): OrderItem
    {
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => $status]);

        return OrderItem::factory()->create(['order_id' => $order->id]);
    }

    public function test_review_requires_verified_purchase(): void
    {
        $user = User::factory()->create();
        $item = $this->purchasedItem($user, OrderStatus::Pending);

        $this->expectException(ReviewRequiresVerifiedPurchaseException::class);

        SubmitReview::run($user, $item, 5, 'Great product');
    }

    public function test_review_is_rejected_for_a_cancelled_order(): void
    {
        $user = User::factory()->create();
        $item = $this->purchasedItem($user, OrderStatus::Cancelled);

        $this->expectException(ReviewRequiresVerifiedPurchaseException::class);

        SubmitReview::run($user, $item, 5, 'Great product');
    }

    public function test_review_is_rejected_when_order_item_belongs_to_a_different_customer(): void
    {
        $owner = User::factory()->create();
        $item = $this->purchasedItem($owner);
        $otherUser = User::factory()->create();

        $this->expectException(ReviewRequiresVerifiedPurchaseException::class);

        SubmitReview::run($otherUser, $item, 5, 'Great product');
    }

    public function test_submitting_a_review_for_a_verified_purchase_succeeds(): void
    {
        $user = User::factory()->create();
        $item = $this->purchasedItem($user);

        $review = SubmitReview::run($user, $item, 4, 'Pretty good', 'Nice');

        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertSame($item->id, $review->order_item_id);
    }

    public function test_a_second_review_for_the_same_order_item_is_rejected(): void
    {
        $user = User::factory()->create();
        $item = $this->purchasedItem($user);
        SubmitReview::run($user, $item, 4, 'First review');

        $this->expectException(DuplicateReviewException::class);

        SubmitReview::run($user, $item, 5, 'Second attempt');
    }

    public function test_editing_review_resets_status_to_pending(): void
    {
        $user = User::factory()->create();
        $item = $this->purchasedItem($user);
        $review = SubmitReview::run($user, $item, 4, 'Original body');
        ModerateReview::run($review, ReviewStatus::Approved);

        EditReview::run($user, $review, 2, 'Changed my mind');

        $this->assertSame(ReviewStatus::Pending, $review->fresh()->status);
        $this->assertSame(2, $review->fresh()->rating);
    }

    public function test_a_customer_cannot_edit_another_customers_review(): void
    {
        $author = User::factory()->create();
        $item = $this->purchasedItem($author);
        $review = SubmitReview::run($author, $item, 4, 'Body');
        $otherUser = User::factory()->create();

        $this->expectException(ReviewOwnershipException::class);

        EditReview::run($otherUser, $review, 1, 'Hijacked');
    }

    public function test_customer_can_delete_own_review(): void
    {
        $user = User::factory()->create();
        $item = $this->purchasedItem($user);
        $review = SubmitReview::run($user, $item, 4, 'Body');

        DeleteReview::run($user, $review);

        $this->assertSoftDeleted($review);
    }

    public function test_admin_can_delete_but_not_edit_another_customers_review(): void
    {
        $author = User::factory()->create();
        $item = $this->purchasedItem($author);
        $review = SubmitReview::run($author, $item, 4, 'Body');
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        DeleteReview::run($admin, $review);

        $this->assertSoftDeleted($review);
    }

    public function test_a_non_author_non_staff_user_cannot_delete_a_review(): void
    {
        $author = User::factory()->create();
        $item = $this->purchasedItem($author);
        $review = SubmitReview::run($author, $item, 4, 'Body');
        $otherUser = User::factory()->create();

        $this->expectException(ReviewOwnershipException::class);

        DeleteReview::run($otherUser, $review);
    }

    public function test_admin_cannot_edit_review_content(): void
    {
        // ModerateReview's signature only accepts a status — it has no way
        // to touch rating/title/body at all, by construction.
        $review = Review::factory()->create(['rating' => 3, 'title' => 'Original', 'body' => 'Original body']);

        ModerateReview::run($review, ReviewStatus::Approved);

        $review->refresh();
        $this->assertSame(3, $review->rating);
        $this->assertSame('Original', $review->title);
        $this->assertSame('Original body', $review->body);
        $this->assertSame(ReviewStatus::Approved, $review->status);
    }
}
