<?php

/**
 * Covers the customer-facing wishlist page (/wishlist) — viewing saved
 * variants, moving one into the cart, and removing one.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Actions\Wishlist\AddToWishlist;
use App\Livewire\Storefront\WishlistPage;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WishlistPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/wishlist')->assertRedirect('/login');
    }

    public function test_an_authenticated_customer_can_view_an_empty_wishlist(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/wishlist')->assertOk()->assertSee('Your wishlist is empty');
    }

    public function test_the_wishlist_page_shows_saved_variants(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create();
        AddToWishlist::run($user, $variant);

        Livewire::test(WishlistPage::class)
            ->assertSee($variant->sku);
    }

    public function test_removing_an_item_takes_it_out_of_the_wishlist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create();
        AddToWishlist::run($user, $variant);

        Livewire::test(WishlistPage::class)
            ->call('removeItem', $variant->id)
            ->assertSee('Your wishlist is empty');

        $this->assertSame(0, $user->wishlistItems()->count());
    }

    public function test_adding_a_wishlisted_variant_to_the_cart_does_not_remove_it_from_the_wishlist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create();
        AddToWishlist::run($user, $variant);

        Livewire::test(WishlistPage::class)
            ->call('addToCart', $variant->id);

        $this->assertSame(1, $user->wishlistItems()->count());
        $cart = Cart::query()->where('user_id', $user->id)->sole();
        $this->assertSame(1, $cart->items()->where('product_variant_id', $variant->id)->sole()->quantity);
    }

    public function test_a_customers_wishlist_page_never_shows_another_customers_items(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherVariant = ProductVariant::factory()->create();
        AddToWishlist::run($otherUser, $otherVariant);

        $this->actingAs($user);

        Livewire::test(WishlistPage::class)
            ->assertDontSee($otherVariant->sku)
            ->assertSee('Your wishlist is empty');
    }
}
