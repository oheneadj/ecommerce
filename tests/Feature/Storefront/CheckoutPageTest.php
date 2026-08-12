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
use App\Enums\CouponType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        // The 'moolre' driver name is overridden with the fake gateway —
        // enabledPaymentProviders() (what the checkout radio list actually
        // reads) goes through the strict-enum-cast PaymentProviderSetting
        // model, so a fictional name like 'fake' can't be enabled there;
        // a real PaymentProvider case with its concrete driver swapped
        // underneath is the only way to keep that model happy in tests.
        $this->app->make(PaymentManager::class)->extend('moolre', fn () => new FakePaymentGateway);
        $this->enableProvider('moolre');
    }

    private function enableProvider(string $provider): void
    {
        DB::table('payment_provider_settings')->insert([
            'provider' => $provider,
            'enabled' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
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

    /**
     * Uploading a logo on the admin Payment Providers screen previously
     * had no visible effect anywhere — enabledPaymentProviders() returned
     * bare PaymentProvider enum cases, not the settings row the logo
     * lives on, so the checkout radio list could only ever show the
     * plain text label.
     */
    public function test_a_providers_logo_is_shown_on_the_checkout_payment_method_list(): void
    {
        Storage::fake('public');
        $logo = UploadedFile::fake()->image('moolre.png');
        $path = (string) $logo->store('payment-providers', 'public');
        DB::table('payment_provider_settings')->where('provider', 'moolre')->update(['logo_path' => $path]);
        AddItemToCart::run(ResolveCurrentCart::run(null, ResolveCurrentCart::guestSessionId()), ProductVariant::factory()->create(['stock' => 10]), 1);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->assertSeeHtml(Storage::disk('public')->url($path));
    }

    public function test_a_provider_with_no_logo_shows_just_its_label(): void
    {
        AddItemToCart::run(ResolveCurrentCart::run(null, ResolveCurrentCart::guestSessionId()), ProductVariant::factory()->create(['stock' => 10]), 1);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->assertSee(PaymentProvider::Moolre->label());
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
        AddItemToCart::run(GetCurrentCart::run($user), ProductVariant::factory()->create(['stock' => 10]), 1);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
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
        AddItemToCart::run(ResolveCurrentCart::run(null, ResolveCurrentCart::guestSessionId()), ProductVariant::factory()->create(['stock' => 10]), 1);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->assertSeeHtml('wire:model.live="selectedShippingMethodId"');
    }

    public function test_changing_the_shipping_method_updates_the_shipping_cost_and_total(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 1000, 'stock' => 5]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 1);

        $cheap = ShippingMethod::factory()->create(['active' => true, 'cost' => 500]);
        $express = ShippingMethod::factory()->create(['active' => true, 'cost' => 2000]);

        $component = Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->assertSet('selectedShippingMethodId', $cheap->id)
            ->assertSet('shippingCost', 500)
            ->assertSee('GH₵5.00');

        $component->set('selectedShippingMethodId', $express->id)
            ->assertSet('shippingCost', 2000)
            ->assertSet('estimatedTotal', $component->get('subtotal') + $component->get('taxEstimate') + 2000)
            ->assertSee('GH₵20.00');
    }

    public function test_applying_a_valid_coupon_shows_the_discount_and_reduces_the_total(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 10000, 'stock' => 5]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        ShippingMethod::factory()->create(['active' => true, 'cost' => 0]);
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 1000]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('couponCode', $coupon->code)
            ->call('applyCoupon')
            ->assertHasNoErrors()
            ->assertSet('discountAmount', 1000)
            ->assertSet('appliedCouponCode', $coupon->code)
            ->assertSee(__('Coupon ":code" applied', ['code' => $coupon->code]));
    }

    public function test_an_invalid_coupon_code_shows_an_error_and_applies_no_discount(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        ShippingMethod::factory()->create(['active' => true]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('couponCode', 'DOES-NOT-EXIST')
            ->call('applyCoupon')
            ->assertHasErrors('couponCode')
            ->assertSet('discountAmount', 0)
            ->assertSet('appliedCouponCode', null);
    }

    public function test_removing_an_applied_coupon_clears_the_discount(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 10000, 'stock' => 5]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        ShippingMethod::factory()->create(['active' => true]);
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 1000]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('couponCode', $coupon->code)
            ->call('applyCoupon')
            ->assertSet('discountAmount', 1000)
            ->call('removeCoupon')
            ->assertSet('discountAmount', 0)
            ->assertSet('appliedCouponCode', null)
            ->assertSet('couponCode', '');
    }

    public function test_editing_the_code_after_applying_it_clears_the_stale_discount(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 10000, 'stock' => 5]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        ShippingMethod::factory()->create(['active' => true]);
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 1000]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('couponCode', $coupon->code)
            ->call('applyCoupon')
            ->assertSet('discountAmount', 1000)
            ->set('couponCode', 'SOMETHING-ELSE')
            ->assertSet('discountAmount', 0)
            ->assertSet('appliedCouponCode', null);
    }

    /**
     * A coupon typed but never actually clicked "Apply" must never
     * silently discount the order — applying is a deliberate step, not
     * an implicit side effect of placing the order.
     */
    public function test_placing_an_order_never_applies_a_typed_but_unapplied_coupon(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 10000, 'stock' => 5]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true, 'cost' => 0]);
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 1000]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->set('couponCode', $coupon->code)
            ->call('placeOrder');

        $order = Order::query()->sole();
        $this->assertNull($order->coupon_id);
        $this->assertSame(0, $order->discount_total);
    }

    public function test_placing_an_order_after_applying_a_coupon_uses_the_applied_discount(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 10000, 'stock' => 5]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true, 'cost' => 0]);
        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 1000]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->set('couponCode', $coupon->code)
            ->call('applyCoupon')
            ->call('placeOrder');

        $order = Order::query()->sole();
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame(1000, $order->discount_total);
    }

    /**
     * A truly-empty cart never reaches this component's form at all
     * anymore — mount() redirects to the cart page before render (see
     * below). This covers the real remaining scenario: the cart was
     * non-empty when the page loaded, but became empty in between (e.g.
     * removed in another tab) before "Place order" was clicked —
     * placeOrder()'s own guard is what catches that race.
     */
    public function test_placing_an_order_with_a_cart_emptied_after_page_load_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        ShippingMethod::factory()->create(['active' => true]);
        $cart = GetCurrentCart::run($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run($cart, $variant, 1);

        $component = Livewire::test(CheckoutPage::class, ['lazy' => false]);
        $cart->items()->delete();

        $component
            ->call('placeOrder')
            ->assertHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_redirects_to_the_cart_page_when_the_cart_is_empty(): void
    {
        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->assertRedirect(route('cart.show'));
    }

    public function test_placing_an_order_without_an_address_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        ShippingMethod::factory()->create(['active' => true]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
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

        Livewire::test(CheckoutPage::class, ['lazy' => false])
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

        Livewire::test(CheckoutPage::class, ['lazy' => false])
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

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder');

        $order = Order::query()->sole();
        $this->assertSame($shippingMethod->id, $order->shipping_method_id);
        $this->assertSame('Express', $order->shipping_method_name);
        $this->assertSame(500, $order->shipping_total);
        $this->assertSame(2500, $order->grand_total);
    }

    public function test_no_gateway_redirect_url_sends_the_customer_to_the_order_confirmation_page(): void
    {
        $this->app->make(PaymentManager::class)->extend('paystack', fn () => new class implements PaymentGateway
        {
            public function initiate(Order $order): PaymentInitiationResult
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
        $this->enableProvider('paystack');

        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->set('paymentProvider', 'paystack')
            ->call('placeOrder')
            ->assertRedirect(route('orders.confirmation', ['order' => Order::query()->sole()]));
    }

    /**
     * Popup checkout mode never redirects the page at all — it dispatches
     * a browser event carrying the access code, and the JS side (not
     * exercised by this backend test) opens Paystack's popup and navigates
     * to the confirmation page itself once it closes.
     */
    public function test_paystack_popup_mode_dispatches_a_browser_event_instead_of_redirecting(): void
    {
        $this->app->make(PaymentManager::class)->extend('paystack', fn () => new class implements PaymentGateway
        {
            public function initiate(Order $order): PaymentInitiationResult
            {
                return new PaymentInitiationResult(success: true, providerReference: 'ref-1', accessCode: 'access-code-1');
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
        DB::table('payment_provider_settings')->insert([
            'provider' => 'paystack',
            'checkout_mode' => 'popup',
            'enabled' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true]);

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->set('paymentProvider', 'paystack')
            ->call('placeOrder')
            ->assertDispatched(
                'paystack-popup-ready',
                accessCode: 'access-code-1',
                confirmationUrl: route('orders.confirmation', ['order' => Order::query()->sole()]),
            )
            ->assertNoRedirect();
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

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder')
            ->assertHasErrors('cart');

        $this->assertSame(1, Order::query()->count());
        $this->assertNull(Auth::user()?->orders()->first()?->payments()->where('status', PaymentStatus::Success)->first());
    }

    /**
     * Regression: previously, the cart "closed" the instant an Order was
     * created for it, regardless of whether payment ever actually
     * started. So a failed payment attempt orphaned that Order forever —
     * the very next `placeOrder` call resolved a brand-new, empty cart
     * and immediately failed with "Your cart is empty," no matter how
     * many times the customer retried. `Cart::scopeOpen()` fixes this:
     * a cart whose order has only `Failed` payments stays open, so
     * retrying reuses the same Order (no duplicate row, no re-reserved
     * stock) and simply attempts payment again.
     */
    public function test_retrying_after_a_failed_payment_reuses_the_same_order_and_can_then_succeed(): void
    {
        FakePaymentGateway::$initiateSucceeds = false;

        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        AddItemToCart::run(GetCurrentCart::run($user), $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true]);

        // First attempt: fails.
        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder')
            ->assertHasErrors('cart');

        $order = Order::query()->sole();
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(PaymentStatus::Failed, $order->payments()->sole()->status);

        // The cart must still be open — same cart, same items — not a
        // fresh empty one.
        $cart = GetCurrentCart::run($user);
        $this->assertSame($order->cart_id, $cart->id);
        $this->assertSame(1, $cart->items()->count());

        // Retry, gateway still down: same order, a second Failed payment
        // attempt, still no duplicate order.
        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder')
            ->assertHasErrors('cart');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame($order->id, Order::query()->sole()->id);
        $this->assertSame(2, $order->payments()->count());

        // Retry again, gateway now working: succeeds against the same order.
        FakePaymentGateway::$initiateSucceeds = true;

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedAddressId', $address->id)
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder')
            ->assertRedirect();

        $this->assertSame(1, Order::query()->count());
        $this->assertTrue($order->payments()->where('status', PaymentStatus::Pending)->exists());

        // Cart is now genuinely closed — a live/successful payment exists.
        $this->assertFalse(Cart::query()->whereKey($cart->id)->open()->exists());
    }

    public function test_a_guest_can_place_an_order_with_manually_entered_details(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'price' => 1000]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true, 'cost' => 300]);

        AddItemToCart::run(
            ResolveCurrentCart::run(null, ResolveCurrentCart::guestSessionId()),
            $variant,
            1,
        );

        Livewire::test(CheckoutPage::class, ['lazy' => false])
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

        AddItemToCart::run(
            ResolveCurrentCart::run(null, ResolveCurrentCart::guestSessionId()),
            $variant,
            1,
        );

        Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder')
            ->assertHasErrors(['guestName', 'guestEmail', 'guestPhone', 'guestLine1', 'guestCity']);

        $this->assertSame(0, Order::query()->count());
    }

    /**
     * Regression test for a bug where every <x-input> on the page showed
     * the *first* validation error in the whole bag (MessageBag::has(null)
     * / first(null) match any/all errors) instead of its own field's
     * error, because the component had no way to know which field it was
     * bound to. Fixed by deriving the error-bag key from the wire:model
     * attribute in resources/views/components/input.blade.php.
     */
    public function test_guest_checkout_errors_are_scoped_to_their_own_field_only(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $shippingMethod = ShippingMethod::factory()->create(['active' => true]);

        AddItemToCart::run(
            ResolveCurrentCart::run(null, ResolveCurrentCart::guestSessionId()),
            $variant,
            1,
        );

        $component = Livewire::test(CheckoutPage::class, ['lazy' => false])
            ->set('selectedShippingMethodId', $shippingMethod->id)
            ->call('placeOrder');

        $html = $component->html();

        // guestName's error should appear exactly once, from <x-input>'s
        // own now-correctly-scoped @error block. Before the fix, every
        // OTHER <x-input> on the page — including fields that are never
        // validated, like the optional line 2/region inputs, and entirely
        // unrelated fields like the coupon code — also rendered this same
        // message, because $attributes->get('name') was always null and
        // @error(null) matches the first error in the whole bag.
        $this->assertSame(1, substr_count($html, 'Please enter your name.'));
    }
}
