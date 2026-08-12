<?php

/**
 * Customer-facing checkout — pick address/shipping, place the order, then
 * hand off to whichever payment provider is currently active.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Cart\ResolveCurrentCart;
use App\Actions\Checkout\CreateOrderFromCart;
use App\Actions\Checkout\PreviewCouponDiscount;
use App\Actions\Payment\InitiatePayment;
use App\Enums\CouponType;
use App\Enums\PaymentStatus;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\PaymentProviderSetting;
use App\Models\ShippingMethod;
use App\Models\StoreSetting;
use App\Payments\PaymentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Cart $cart
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, ShippingMethod> $shippingMethods
 * @property-read Collection<int, PaymentProviderSetting> $enabledPaymentProviders
 * @property-read int $subtotal
 * @property-read int $taxEstimate
 * @property-read int $shippingCost
 * @property-read int $effectiveShippingCost
 * @property-read int $estimatedTotal
 * @property-read Coupon|null $appliedCoupon
 */
#[Title('Checkout')]
#[Lazy]
class CheckoutPage extends Component
{
    public ?int $selectedAddressId = null;

    public ?int $selectedShippingMethodId = null;

    public string $couponCode = '';

    /**
     * The coupon code actually validated and applied via the "Apply"
     * button — deliberately separate from `$couponCode` (what's currently
     * typed) so editing the text field after applying doesn't silently
     * keep discounting the order under a code the customer may have
     * changed their mind about, or place an order under a code that was
     * never actually validated.
     */
    public ?string $appliedCouponCode = null;

    public int $discountAmount = 0;

    public ?string $paymentProvider = null;

    // Guest checkout only (BRD FR-3.2/FR-3.3) — a guest has no saved
    // Address to select, so they fill these in directly at checkout.
    public string $guestName = '';

    public string $guestEmail = '';

    public string $guestPhone = '';

    public string $guestLine1 = '';

    public string $guestLine2 = '';

    public string $guestCity = '';

    public string $guestRegion = '';

    public function mount(): void
    {
        if (Auth::check()) {
            $this->selectedAddressId = Auth::user()->addresses()->where('is_default', true)->value('id')
                ?? Auth::user()->addresses()->value('id');
        }

        $this->selectedShippingMethodId = ShippingMethod::query()->where('active', true)->orderBy('cost')->value('id');
        $this->paymentProvider = app(PaymentManager::class)->getDefaultDriver();
    }

    #[Computed]
    public function cart(): Cart
    {
        $cart = ResolveCurrentCart::run(Auth::user(), ResolveCurrentCart::guestSessionId());
        $cart->load('items.productVariant.product');

        return $cart;
    }

    /**
     * @return Collection<int, Address>
     */
    #[Computed]
    public function addresses(): Collection
    {
        if (! Auth::check()) {
            return new Collection;
        }

        return Auth::user()->addresses()->latest('is_default')->latest('id')->get();
    }

    /**
     * @return Collection<int, ShippingMethod>
     */
    #[Computed]
    public function shippingMethods(): Collection
    {
        return ShippingMethod::query()->where('active', true)->orderBy('cost')->get();
    }

    #[Computed]
    public function subtotal(): int
    {
        return $this->cart->items->sum(fn ($item) => $item->productVariant->price * $item->quantity);
    }

    #[Computed]
    public function taxEstimate(): int
    {
        return (int) round($this->subtotal * StoreSetting::current()->tax_rate / 100);
    }

    #[Computed]
    public function shippingCost(): int
    {
        $method = $this->shippingMethods->firstWhere('id', $this->selectedShippingMethodId);

        return $method !== null ? $method->cost : 0;
    }

    /**
     * `$shippingCost` itself never changes — this is what's actually
     * charged, zeroed by a FreeShipping coupon exactly like
     * `ApplyCouponToOrder`/`CreateOrderFromCart` zero it for real. Used
     * by both the order summary's Shipping line and `estimatedTotal()`,
     * so the two can never show inconsistent numbers.
     */
    #[Computed]
    public function effectiveShippingCost(): int
    {
        return $this->appliedCoupon?->type === CouponType::FreeShipping ? 0 : $this->shippingCost;
    }

    #[Computed]
    public function estimatedTotal(): int
    {
        return $this->subtotal - $this->discountAmount + $this->taxEstimate + $this->effectiveShippingCost;
    }

    #[Computed]
    public function appliedCoupon(): ?Coupon
    {
        return $this->appliedCouponCode !== null
            ? Coupon::query()->where('code', $this->appliedCouponCode)->first()
            : null;
    }

    /**
     * The providers a Super Admin has enabled from the Payment Providers
     * admin screen, in their configured display order — what the customer
     * actually gets to choose between at checkout. Returns the full
     * settings row (not just the bare enum) so the view can show each
     * provider's logo, not just its label.
     *
     * @return Collection<int, PaymentProviderSetting>
     */
    #[Computed]
    public function enabledPaymentProviders(): Collection
    {
        return PaymentProviderSetting::query()->enabledOrdered()->get();
    }

    /**
     * Validates the typed code against the current cart and, if valid,
     * previews its discount — no order exists yet, so nothing is
     * persisted here (see `PreviewCouponDiscount`). The authoritative
     * check happens again, locked, when the order is actually placed.
     */
    public function applyCoupon(): void
    {
        $this->resetErrorBag('couponCode');

        if (trim($this->couponCode) === '') {
            $this->addError('couponCode', 'Please enter a coupon code.');

            return;
        }

        try {
            $result = PreviewCouponDiscount::run(
                $this->cart,
                $this->couponCode,
                Auth::id(),
                Auth::check() ? null : ($this->guestEmail !== '' ? $this->guestEmail : null),
            );
        } catch (InvalidCouponException|CouponUsageLimitExceededException $e) {
            $this->addError('couponCode', $e->getMessage());

            return;
        }

        $this->appliedCouponCode = $result['coupon']->code;
        $this->discountAmount = $result['discount'];
        unset($this->appliedCoupon);
    }

    public function removeCoupon(): void
    {
        $this->couponCode = '';
        $this->appliedCouponCode = null;
        $this->discountAmount = 0;
        $this->resetErrorBag('couponCode');
        unset($this->appliedCoupon);
    }

    /**
     * Editing the code after applying it invalidates the preview — the
     * discount shown must always match a code that was actually
     * validated, never a stale amount sitting under newly-typed text.
     */
    public function updatedCouponCode(): void
    {
        if ($this->appliedCouponCode !== null && $this->couponCode !== $this->appliedCouponCode) {
            $this->appliedCouponCode = null;
            $this->discountAmount = 0;
            unset($this->appliedCoupon);
        }
    }

    public function placeOrder(): void
    {
        if ($this->cart->items->isEmpty()) {
            $this->addError('cart', 'Your cart is empty.');

            return;
        }

        if (! $this->selectedShippingMethodId) {
            $this->addError('selectedShippingMethodId', 'Please select a shipping method.');

            return;
        }

        // Re-validated against the currently enabled set, not just trusted
        // from the posted value — a provider disabled by the Super Admin
        // between page load and submit must never sneak through.
        if ($this->paymentProvider === null || ! $this->enabledPaymentProviders->contains(fn (PaymentProviderSetting $setting): bool => $setting->provider->value === $this->paymentProvider)) {
            $this->addError('paymentProvider', 'Please select a payment method.');

            return;
        }

        if (Auth::check()) {
            if (! $this->selectedAddressId) {
                $this->addError('selectedAddressId', 'Please select or add a delivery address.');

                return;
            }

            $address = Address::query()->findOrFail($this->selectedAddressId);
            $this->authorize('view', $address);
        } else {
            if (! $this->validateGuestDetails()) {
                return;
            }

            $address = Address::query()->create([
                'user_id' => null,
                'recipient_name' => $this->guestName,
                'phone' => $this->guestPhone,
                'line1' => $this->guestLine1,
                'line2' => $this->guestLine2 !== '' ? $this->guestLine2 : null,
                'city' => $this->guestCity,
                'region' => $this->guestRegion !== '' ? $this->guestRegion : null,
            ]);
        }

        $shippingMethod = ShippingMethod::query()->findOrFail($this->selectedShippingMethodId);

        try {
            $order = CreateOrderFromCart::run(
                $this->cart,
                $address,
                guestEmail: Auth::check() ? null : $this->guestEmail,
                guestPhone: Auth::check() ? null : $this->guestPhone,
                couponCode: $this->appliedCouponCode,
                shippingMethod: $shippingMethod,
            );
        } catch (EmptyCartException|InsufficientStockException|InvalidCouponException|CouponUsageLimitExceededException $e) {
            $this->addError('cart', $e->getMessage());

            return;
        }

        $payment = InitiatePayment::run($order, $this->paymentProvider);

        if ($payment->status === PaymentStatus::Failed) {
            $this->addError('cart', $payment->metadata['error'] ?? 'Payment could not be started. Please try again.');

            return;
        }

        $providerSetting = PaymentProviderSetting::query()->where('provider', $this->paymentProvider)->first();
        $accessCode = $payment->metadata['access_code'] ?? null;

        // Popup checkout never leaves this page — the JS side (see
        // resources/js/paystack-popup.js) opens Paystack's popup and
        // navigates to the confirmation page itself once it closes,
        // regardless of what the popup itself reports, since only the
        // webhook + VerifyPendingPayments polling fallback are trusted to
        // confirm payment actually succeeded.
        if ($providerSetting?->usesPaystackPopup() && $accessCode !== null) {
            $this->dispatch(
                'paystack-popup-ready',
                accessCode: $accessCode,
                publicKey: config('payments.providers.paystack.public_key'),
                confirmationUrl: route('orders.confirmation', ['order' => $order]),
            );

            return;
        }

        $redirectUrl = $payment->metadata['redirect_url'] ?? null;

        if ($redirectUrl) {
            $this->redirect($redirectUrl, navigate: false);

            return;
        }

        $this->redirectRoute('orders.confirmation', ['order' => $order], navigate: true);
    }

    private function validateGuestDetails(): bool
    {
        $fields = [
            'guestName' => 'Please enter your name.',
            'guestEmail' => 'Please enter your email address.',
            'guestPhone' => 'Please enter your phone number.',
            'guestLine1' => 'Please enter your delivery address.',
            'guestCity' => 'Please enter your city.',
        ];

        $valid = true;

        foreach ($fields as $field => $message) {
            if (trim($this->{$field}) === '') {
                $this->addError($field, $message);
                $valid = false;
            }
        }

        return $valid;
    }

    public function render(): View
    {
        return view('livewire.storefront.checkout-page');
    }

    /**
     * Shown until this component's own follow-up request resolves — a
     * skeleton matching the real page's two-column layout so there's no
     * layout shift when it swaps in.
     */
    public function placeholder(): View
    {
        return view('livewire.storefront.checkout-page-placeholder');
    }
}
