<?php

/**
 * Covers the customer-facing cart page (/cart) — viewing items, changing
 * quantity, removing a line, and that GetCurrentCart resolves the same
 * still-open cart consistently rather than creating a new one each time.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\GetCurrentCart;
use App\Actions\Checkout\CreateOrderFromCart;
use App\Livewire\Storefront\CartPage;
use App\Models\Address;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_view_an_empty_cart(): void
    {
        $this->get('/cart')->assertOk()->assertSee('Your cart is empty');
    }

    public function test_an_authenticated_customer_can_view_an_empty_cart(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cart')->assertOk()->assertSee('Your cart is empty');
    }

    public function test_get_current_cart_creates_one_when_the_user_has_none(): void
    {
        $user = User::factory()->create();

        $cart = GetCurrentCart::run($user);

        $this->assertSame($user->id, $cart->user_id);
        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'user_id' => $user->id]);
    }

    public function test_get_current_cart_resolves_the_same_open_cart_repeatedly(): void
    {
        $user = User::factory()->create();

        $first = GetCurrentCart::run($user);
        $second = GetCurrentCart::run($user);

        $this->assertSame($first->id, $second->id);
    }

    public function test_get_current_cart_starts_a_new_cart_after_the_old_one_checked_out(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $checkedOutCart = GetCurrentCart::run($user);
        AddItemToCart::run($checkedOutCart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id]);
        CreateOrderFromCart::run($checkedOutCart, $address);

        $newCart = GetCurrentCart::run($user);

        $this->assertNotSame($checkedOutCart->id, $newCart->id);
    }

    public function test_the_cart_page_shows_items_and_subtotal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 1500]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 2);

        Livewire::test(CartPage::class)
            ->assertSee($variant->sku)
            ->assertSee('GH₵30.00');
    }

    public function test_updating_quantity_changes_the_line_and_subtotal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 1000]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 1);

        Livewire::test(CartPage::class)
            ->call('updateQuantity', $variant->id, 3)
            ->assertSee('GH₵30.00');

        $this->assertSame(3, $cart->items()->sole()->quantity);
    }

    public function test_setting_quantity_to_zero_removes_the_item(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create();
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 1);

        Livewire::test(CartPage::class)
            ->call('updateQuantity', $variant->id, 0);

        $this->assertSame(0, $cart->items()->count());
    }

    public function test_removing_an_item_takes_it_out_of_the_cart(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create();
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 1);

        Livewire::test(CartPage::class)
            ->call('removeItem', $variant->id)
            ->assertSee('Your cart is empty');

        $this->assertSame(0, $cart->items()->count());
    }

    public function test_a_customers_cart_page_never_shows_another_customers_items(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherVariant = ProductVariant::factory()->create();
        $otherCart = Cart::factory()->create(['user_id' => $otherUser->id]);
        AddItemToCart::run($otherCart, $otherVariant, 1);

        $this->actingAs($user);

        Livewire::test(CartPage::class)
            ->assertDontSee($otherVariant->sku)
            ->assertSee('Your cart is empty');
    }
}
