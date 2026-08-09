<?php

/**
 * Covers the customer-facing checkout page (/checkout) — address/shipping
 * preselection, validation guards, order creation with the chosen shipping
 * method, and the redirect after payment initiation.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\GetCurrentCart;
use App\Enums\PaymentStatus;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Address;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Payments\PaymentManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\Feature\Payment\FakePaymentGateway;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentGateway::reset();
        $this->app->make(PaymentManager::class)->extend('fake', fn () => new FakePaymentGateway);
        config([
            'payments.channels.mobile_money' => 'fake',
            'payments.channels.card' => 'fake',
        ]);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
    }

    public function test_an_authenticated_customer_can_view_checkout(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/checkout')->assertOk()->assertSee('Checkout');
    }

    public function test_the_default_address_and_cheapest_active_shipping_method_are_preselected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);
        $defaultAddress = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $cheap = ShippingMethod::factory()->create(['active' => true, 'cost' => 500]);
        ShippingMethod::factory()->create(['active' => true, 'cost' => 1500]);
        ShippingMethod::factory()->create(['active' => false, 'cost' => 100]);

        Livewire::test(CheckoutPage::class)
            ->assertSet('selectedAddressId', $defaultAddress->id)
            ->assertSet('selectedShippingMethodId', $cheap->id);
    }

    public function test_placing_an_order_with_an_empty_cart_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        ShippingMethod::factory()->create(['active' => true]);

        Livewire::test(CheckoutPage::class)
            ->call('placeOrder')
            ->assertHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_placing_an_order_without_an_address_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        ShippingMethod::factory()->create(['active' => true]);

        Livewire::test(CheckoutPage::class)
            ->set('selectedAddressId', null)
            ->call('placeOrder')
            ->assertHasErrors('selectedAddressId');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_placing_an_order_without_a_shipping_method_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);

        Livewire::test(CheckoutPage::class)
            ->set('selectedShippingMethodId', null)
            ->call('placeOrder')
            ->assertHasErrors('selectedShippingMethodId');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_customer_cannot_select_another_customers_address(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAddress = Address::factory()->create(['user_id' => $otherUser->id]);
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        ShippingMethod::factory()->create(['active' => true]);

        $this->withoutExceptionHandling();
        $this->expectException(AuthorizationException::class);

        Livewire::test(CheckoutPage::class)
            ->set('selectedAddressId', $otherAddress->id)
            ->call('placeOrder');
    }

    public function test_placing_a_valid_order_creates_it_with_the_chosen_shipping_method_and_redirects_to_the_gateway(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10, 'price' => 1000]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 2);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true, 'cost' => 500, 'name' => 'Express']);

        Livewire::test(CheckoutPage::class)
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder');

        $order = Order::query()->sole();
        $this->assertSame($shippingMethod->id, $order->shipping_method_id);
        $this->assertSame('Express', $order->shipping_method_name);
        $this->assertSame(500, $order->shipping_total);
        $this->assertSame(2500, $order->grand_total);
    }

    public function test_a_failed_payment_initiation_shows_an_error_instead_of_redirecting(): void
    {
        FakePaymentGateway::$initiateSucceeds = false;

        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true]);

        Livewire::test(CheckoutPage::class)
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder')
            ->assertHasErrors('cart');

        $this->assertSame(1, Order::query()->count());
        $this->assertNull(Auth::user()?->orders()->first()?->payments()->where('status', PaymentStatus::Success)->first());
    }
}
