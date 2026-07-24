<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\MergeGuestCartIntoUser;
use App\Actions\Cart\RemoveItemFromCart;
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
        $item = AddItemToCart::run($cart, $variant, 1);

        RemoveItemFromCart::run($item);

        $this->assertSame(0, $cart->items()->count());
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
}
