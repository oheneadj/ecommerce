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
use App\Enums\PaymentStatus;
use App\Livewire\Storefront\CartPage;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The route itself (and the real component's content, once its
     * #[Lazy] follow-up request resolves — forced here via the built-in
     * `$refresh` no-op action, same as any other post-mount interaction).
     */
    public function test_a_guest_can_view_an_empty_cart(): void
    {
        $this->get('/cart')->assertOk();

        Livewire::test(CartPage::class)->call('$refresh')->assertSee('Your cart is empty');
    }

    public function test_an_authenticated_customer_can_view_an_empty_cart(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cart')->assertOk();

        Livewire::test(CartPage::class)->call('$refresh')->assertSee('Your cart is empty');
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

    /**
     * A cart only truly "closes" once its order has a payment actually in
     * flight or settled (see Cart::scopeOpen()) — an order with no
     * payment attempt yet, or only failed ones, isn't a real checkout,
     * it's still in progress. Covered fully at the model level in
     * tests/Feature/Cart/CartScopeOpenTest.php; this asserts the same
     * thing through the Action customers actually go through.
     */
    public function test_get_current_cart_starts_a_new_cart_only_once_the_old_one_has_a_payment_in_progress(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $checkedOutCart = GetCurrentCart::run($user);
        AddItemToCart::run($checkedOutCart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $order = CreateOrderFromCart::run($checkedOutCart, $address);

        // The order exists but no payment has been attempted yet — still
        // mid-checkout, so the same cart must keep resolving.
        $this->assertSame($checkedOutCart->id, GetCurrentCart::run($user)->id);

        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Pending]);

        $newCart = GetCurrentCart::run($user);

        $this->assertNotSame($checkedOutCart->id, $newCart->id);
    }

    public function test_the_cart_page_shows_items_and_subtotal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 1500, 'stock' => 10]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 2);

        Livewire::test(CartPage::class)
            ->call('$refresh')
            ->assertSee($variant->sku)
            ->assertSee('GH₵30.00');
    }

    /**
     * Regression: an admin discontinuing a variant (soft-delete) left it
     * sitting in an existing cart forever — `productVariant()` then
     * resolves to null (excluded by the default soft-delete scope), which
     * the page dereferenced unguarded and crashed with a 500, with no
     * self-service way for the customer to clear it. The stale item is
     * now pruned automatically instead.
     */
    public function test_a_soft_deleted_variant_is_pruned_from_the_cart_instead_of_crashing_the_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $keptVariant = ProductVariant::factory()->create(['price' => 1000, 'stock' => 10]);
        $deletedVariant = ProductVariant::factory()->create(['price' => 500, 'stock' => 10]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $keptVariant, 1);
        AddItemToCart::run($cart, $deletedVariant, 1);
        $deletedVariant->delete();

        Livewire::test(CartPage::class)
            ->call('$refresh')
            ->assertOk()
            ->assertSee($keptVariant->sku)
            ->assertSee('GH₵10.00');

        $this->assertSame(1, $cart->fresh()->items()->count());
    }

    public function test_updating_quantity_changes_the_line_and_subtotal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 1000, 'stock' => 10]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 1);

        Livewire::test(CartPage::class)
            ->call('updateQuantity', $variant->id, 3)
            ->assertSee('GH₵30.00');

        $this->assertSame(3, $cart->items()->sole()->quantity);
    }

    public function test_updating_quantity_above_stock_shows_an_error_and_does_not_change_the_line(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 2]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 2);

        Livewire::test(CartPage::class)
            ->call('updateQuantity', $variant->id, 3)
            ->assertDispatched('toast', variant: 'error', message: 'Only 2 left in stock.');

        $this->assertSame(2, $cart->items()->sole()->quantity);
    }

    public function test_setting_quantity_to_zero_removes_the_item(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 5]);
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
        $variant = ProductVariant::factory()->create(['stock' => 5]);
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
        $otherVariant = ProductVariant::factory()->create(['stock' => 5]);
        $otherCart = Cart::factory()->create(['user_id' => $otherUser->id]);
        AddItemToCart::run($otherCart, $otherVariant, 1);

        $this->actingAs($user);

        Livewire::test(CartPage::class)
            ->call('$refresh')
            ->assertDontSee($otherVariant->sku)
            ->assertSee('Your cart is empty');
    }

    /**
     * The other half of the empty-cart confusion fix: landing on /cart
     * with nothing in it can mean the customer's real cart is only
     * "empty" because its order still has a payment in flight — redirect
     * to that order's own status page instead of a bare empty cart.
     */
    public function test_a_customer_whose_cart_closed_on_a_pending_payment_is_redirected_to_the_order(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $order = CreateOrderFromCart::run($cart, $address);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Pending]);

        $this->actingAs($user);

        Livewire::test(CartPage::class, ['lazy' => false])
            ->assertRedirect(route('orders.confirmation', ['order' => $order]));
    }

    public function test_a_customer_whose_cart_closed_on_a_failed_payment_is_redirected_to_the_order(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $order = CreateOrderFromCart::run($cart, $address);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Failed]);
        // A cart with only a Failed payment stays "open" per scopeOpen()
        // — force it closed the way GetCurrentCart would find it, by
        // deleting the items directly (simulating the customer having
        // genuinely nothing left after the attempt, e.g. retried until a
        // Pending attempt existed then it later flipped to Failed).
        $cart->items()->delete();

        $this->actingAs($user);

        Livewire::test(CartPage::class, ['lazy' => false])
            ->assertRedirect(route('orders.confirmation', ['order' => $order]));
    }

    public function test_a_genuinely_empty_cart_shows_a_nudge_to_order_history_for_a_customer(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CartPage::class)
            ->call('$refresh')
            ->assertSee('Check your order history');
    }

    public function test_a_genuinely_empty_cart_shows_no_nudge_for_a_guest(): void
    {
        Livewire::test(CartPage::class)
            ->call('$refresh')
            ->assertDontSee('Check your order history');
    }
}
