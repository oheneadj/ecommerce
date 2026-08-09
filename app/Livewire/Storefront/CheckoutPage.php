<?php

/**
 * Customer-facing checkout — pick address/shipping/payment channel, place
 * the order, then hand off to the payment provider.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Cart\GetCurrentCart;
use App\Actions\Checkout\CreateOrderFromCart;
use App\Actions\Payment\InitiatePayment;
use App\Enums\PaymentStatus;
use App\Exceptions\CouponUsageLimitExceededException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\StoreSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Cart $cart
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, ShippingMethod> $shippingMethods
 * @property-read int $subtotal
 * @property-read int $taxEstimate
 * @property-read int $shippingCost
 * @property-read int $estimatedTotal
 */
#[Title('Checkout')]
class CheckoutPage extends Component
{
    public ?int $selectedAddressId = null;

    public ?int $selectedShippingMethodId = null;

    public string $couponCode = '';

    public string $channel = 'mobile_money';

    public function mount(): void
    {
        $this->selectedAddressId = Auth::user()->addresses()->where('is_default', true)->value('id')
            ?? Auth::user()->addresses()->value('id');

        $this->selectedShippingMethodId = ShippingMethod::query()->where('active', true)->orderBy('cost')->value('id');
    }

    #[Computed]
    public function cart(): Cart
    {
        $cart = GetCurrentCart::run(Auth::user());
        $cart->load('items.productVariant.product');

        return $cart;
    }

    /**
     * @return Collection<int, Address>
     */
    #[Computed]
    public function addresses(): Collection
    {
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

    #[Computed]
    public function estimatedTotal(): int
    {
        return $this->subtotal + $this->taxEstimate + $this->shippingCost;
    }

    public function placeOrder(): void
    {
        if ($this->cart->items->isEmpty()) {
            $this->addError('cart', 'Your cart is empty.');

            return;
        }

        if (! $this->selectedAddressId) {
            $this->addError('selectedAddressId', 'Please select or add a delivery address.');

            return;
        }

        if (! $this->selectedShippingMethodId) {
            $this->addError('selectedShippingMethodId', 'Please select a shipping method.');

            return;
        }

        $address = Address::query()->findOrFail($this->selectedAddressId);
        $this->authorize('view', $address);

        $shippingMethod = ShippingMethod::query()->findOrFail($this->selectedShippingMethodId);

        try {
            $order = CreateOrderFromCart::run(
                $this->cart,
                $address,
                couponCode: $this->couponCode !== '' ? $this->couponCode : null,
                shippingMethod: $shippingMethod,
            );
        } catch (EmptyCartException|InsufficientStockException|InvalidCouponException|CouponUsageLimitExceededException $e) {
            $this->addError('cart', $e->getMessage());

            return;
        }

        $payment = InitiatePayment::run($order, $this->channel);

        if ($payment->status === PaymentStatus::Failed) {
            $this->addError('cart', $payment->metadata['error'] ?? 'Payment could not be started. Please try again.');

            return;
        }

        $redirectUrl = $payment->metadata['redirect_url'] ?? null;

        if ($redirectUrl) {
            $this->redirect($redirectUrl, navigate: false);

            return;
        }

        $this->redirectRoute('account.show', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.storefront.checkout-page');
    }
}
