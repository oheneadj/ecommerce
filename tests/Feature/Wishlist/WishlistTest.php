<?php

declare(strict_types=1);

namespace Tests\Feature\Wishlist;

use App\Actions\Wishlist\AddToWishlist;
use App\Actions\Wishlist\RemoveFromWishlist;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_add_a_variant_to_their_wishlist(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        AddToWishlist::run($user, $variant);

        $this->assertSame(1, $user->wishlistItems()->count());
    }

    public function test_adding_the_same_variant_twice_is_not_a_duplicate(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        AddToWishlist::run($user, $variant);
        AddToWishlist::run($user, $variant);

        $this->assertSame(1, $user->wishlistItems()->count());
    }

    public function test_customer_can_remove_a_variant_from_their_wishlist(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        AddToWishlist::run($user, $variant);

        RemoveFromWishlist::run($user, $variant);

        $this->assertSame(0, $user->wishlistItems()->count());
    }

    public function test_wishlist_is_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        AddToWishlist::run($userA, $variant);

        $this->assertSame(1, $userA->wishlistItems()->count());
        $this->assertSame(0, $userB->wishlistItems()->count());
    }
}
