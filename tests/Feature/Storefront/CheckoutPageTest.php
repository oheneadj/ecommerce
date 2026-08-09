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
use App\Actions\Cart\ResolveCurrentCart;
use App\Enums\PaymentStatus;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentInitiationResult;
use App\Payments\PaymentManager;
use App\Payments\PaymentVerificationResult;
use App\Payments\RefundResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    public function test_a_guest_can_view_checkout(): void
    {
        $this->get('/checkout')->assertOk()->assertSee('Checkout');
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

    /**
     * Regression: the shipping radio input previously used plain
     * `wire:model`, which only syncs to the server on the *next* network
     * request rather than immediately — so switching shipping methods
     * never actually re-rendered the order summary's shipping/total
     * figures until some other action fired. It must be `wire:model.live`
     * since `shippingCost`/`estimatedTotal` are computed properties that
     * only recompute on render.
     */
    public function test_the_shipping_radio_input_is_live_bound_so_the_summary_updates_immediately(): void
    {
        ShippingMethod::factory()->create(['active' => true]);

        Livewire::test(CheckoutPage::class)
            ->assertSeeHtml('wire:model.live="selectedShippingMethodId"');
    }

    public function test_changing_the_shipping_method_updates_the_shipping_cost_and_total(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 1000]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 1);

        $cheap = ShippingMethod::factory()->create(['active' => true, 'cost' => 500]);
        $express = ShippingMethod::factory()->create(['active' => true, 'cost' => 2000]);

        $component = Livewire::test(CheckoutPage::class)
            ->assertSet('selectedShippingMethodId', $cheap->id)
            ->assertSet('shippingCost', 500)
            ->assertSee('GH₵5.00');

        $component->set('selectedShippingMethodId', $express->id)
            ->assertSet('shippingCost', 2000)
            ->assertSet('estimatedTotal', $component->get('subtotal') + $component->get('taxEstimate') + 2000)
            ->assertSee('GH₵20.00');
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

    public function test_a_channel_with_no_gateway_redirect_url_sends_the_customer_to_the_order_confirmation_page(): void
    {
        $this->app->make(PaymentManager::class)->extend('no_redirect', fn () => new class implements PaymentGateway
        {
            public function initiate(Order $order, string $channel): PaymentInitiationResult
            {
                return new PaymentInitiationResult(success: true, providerReference: 'ref-1');
            }

            public function verify(string $providerReference): PaymentVerificationResult
            {
                return new PaymentVerificationResult(status: PaymentStatus::Pending, providerReference: $providerReference);
            }

            public function refund(Payment $payment, int $amount, ?string $reason = null): RefundResult
            {
                return new RefundResult(success: true);
            }

            public function verifyWebhookSignature(Request $request): bool
            {
                return true;
            }

            public function webhookEventId(Request $request): string
            {
                return 'event-1';
            }

            public function paymentReferenceFromWebhook(Request $request): ?string
            {
                return null;
            }
        });
        config(['payments.channels.mobile_money' => 'no_redirect']);

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
            ->assertRedirect(route('orders.confirmation', ['order' => Order::query()->sole()]));
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

    public function test_a_guest_can_place_an_order_with_manually_entered_details(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'price' => 1000]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true, 'cost' => 300]);

        $component = Livewire::test(CheckoutPage::class);
        AddItemToCart::run(
            ResolveCurrentCart::run(null, ResolveCurrentCart::guestSessionId()),
            $variant,
            1,
        );

        $component
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->set('guestName', 'Ama Boateng')
            ->set('guestEmail', 'ama@example.com')
            ->set('guestPhone', '0244000000')
            ->set('guestLine1', '12 Ring Road')
            ->set('guestCity', 'Accra')
            ->call('placeOrder');

        $order = Order::query()->sole();
        $this->assertNull($order->user_id);
        $this->assertSame('ama@example.com', $order->guest_email);
        $this->assertSame('0244000000', $order->guest_phone);
        $this->assertSame('Ama Boateng', $order->address_snapshot['recipient_name']);
        $this->assertSame(1300, $order->grand_total);
    }

    public function test_a_guest_checkout_is_rejected_without_required_details(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true]);

        $component = Livewire::test(CheckoutPage::class);
        AddItemToCart::run(
            ResolveCurrentCart::run(null, ResolveCurrentCart::guestSessionId()),
            $variant,
            1,
        );

        $component
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder')
            ->assertHasErrors(['guestName', 'guestEmail', 'guestPhone', 'guestLine1', 'guestCity']);

        $this->assertSame(0, Order::query()->count());
    }
}
