<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\MergeGuestCartIntoUser;
use App\Actions\Cart\RemoveItemFromCart;
use App\Actions\Cart\UpdateCartItemQuantity;
use App\Exceptions\CartQuantityExceedsStockException;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_to_cart_does_not_affect_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create();

        AddItemToCart::run($cart, $variant, 3);

        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_adding_the_same_variant_twice_increases_quantity_instead_of_duplicating(): void
    {
        $variant = ProductVariant::factory()->create();
        $cart = Cart::factory()->create();

        AddItemToCart::run($cart, $variant, 2);
        AddItemToCart::run($cart, $variant, 3);

        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(5, $cart->items()->first()->quantity);
    }

    public function test_removing_an_item_deletes_it_from_the_cart(): void
    {
        $variant = ProductVariant::factory()->create();
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);

        RemoveItemFromCart::run($cart, $variant);

        $this->assertSame(0, $cart->items()->count());
    }

    public function test_removing_an_item_from_a_cart_the_variant_is_not_in_does_nothing(): void
    {
        $variant = ProductVariant::factory()->create();
        $otherVariant = ProductVariant::factory()->create();
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);

        RemoveItemFromCart::run($cart, $otherVariant);

        $this->assertSame(1, $cart->items()->count());
    }

    public function test_removing_an_item_cannot_reach_another_carts_line(): void
    {
        // RemoveItemFromCart takes the owning Cart and scopes the delete
        // through it — it has no way to be pointed at a CartItem row from
        // an unrelated cart, unlike a bare-CartItem signature would.
        $variant = ProductVariant::factory()->create();
        $ownCart = Cart::factory()->create();
        $otherCart = Cart::factory()->create();
        AddItemToCart::run($otherCart, $variant, 1);

        RemoveItemFromCart::run($ownCart, $variant);

        $this->assertSame(1, $otherCart->items()->count());
    }

    public function test_merging_a_guest_cart_into_an_existing_user_cart_combines_quantities(): void
    {
        $variant = ProductVariant::factory()->create();
        $user = User::factory()->create();
        $userCart = Cart::factory()->create(['user_id' => $user->id]);
        AddItemToCart::run($userCart, $variant, 2);

        $guestCart = Cart::factory()->create(['user_id' => null, 'session_id' => 'guest-session']);
        AddItemToCart::run($guestCart, $variant, 3);

        $result = MergeGuestCartIntoUser::run($guestCart, $user);

        $this->assertSame($userCart->id, $result->id);
        $this->assertSame(5, $result->items()->first()->quantity);
        $this->assertModelMissing($guestCart);
    }

    public function test_merging_a_guest_cart_when_user_has_no_existing_cart_reassigns_it(): void
    {
        $user = User::factory()->create();
        $guestCart = Cart::factory()->create(['user_id' => null, 'session_id' => 'guest-session']);
        $variant = ProductVariant::factory()->create();
        AddItemToCart::run($guestCart, $variant, 1);

        $result = MergeGuestCartIntoUser::run($guestCart, $user);

        $this->assertSame($guestCart->id, $result->id);
        $this->assertSame($user->id, $result->fresh()->user_id);
    }

    public function test_adding_more_than_available_stock_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 3]);
        $cart = Cart::factory()->create();

        $this->expectException(CartQuantityExceedsStockException::class);

        AddItemToCart::run($cart, $variant, 4);
    }

    public function test_adding_to_cart_twice_cannot_exceed_stock_in_total(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 3);

        $this->expectException(CartQuantityExceedsStockException::class);

        AddItemToCart::run($cart, $variant, 3);
    }

    public function test_adding_exactly_the_remaining_stock_succeeds(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $cart = Cart::factory()->create();

        AddItemToCart::run($cart, $variant, 5);

        $this->assertSame(5, $cart->items()->first()->quantity);
    }

    public function test_updating_quantity_above_available_stock_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 2]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);

        $this->expectException(CartQuantityExceedsStockException::class);

        UpdateCartItemQuantity::run($cart, $variant, 3);
    }

    public function test_updating_quantity_to_the_exact_stock_amount_succeeds(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 2]);
        $cart = Cart::factory()->create();
        AddItemToCart::run($cart, $variant, 1);

        UpdateCartItemQuantity::run($cart, $variant, 2);

        $this->assertSame(2, $cart->items()->first()->quantity);
    }
}
