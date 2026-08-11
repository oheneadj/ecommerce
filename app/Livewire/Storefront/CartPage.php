<?php

/**
 * Customer-facing cart — view items, change quantity, remove a line.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Cart\RemoveItemFromCart;
use App\Actions\Cart\ResolveCurrentCart;
use App\Actions\Cart\UpdateCartItemQuantity;
use App\Exceptions\CartQuantityExceedsStockException;
use App\Models\Cart;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Cart $cart
 * @property-read int $subtotal
 */
#[Title('My Cart')]
#[Lazy]
class CartPage extends Component
{
    #[Computed]
    public function cart(): Cart
    {
        $cart = ResolveCurrentCart::run(Auth::user(), ResolveCurrentCart::guestSessionId());
        $cart->load(['items.productVariant.product', 'items.productVariant.images', 'items.productVariant.product.images']);

        return $cart;
    }

    #[Computed]
    public function subtotal(): int
    {
        return $this->cart->items->sum(fn ($item) => $item->productVariant->price * $item->quantity);
    }

    public function updateQuantity(int $variantId, int $quantity): void
    {
        $variant = ProductVariant::query()->findOrFail($variantId);

        try {
            UpdateCartItemQuantity::run($this->cart, $variant, $quantity);
        } catch (CartQuantityExceedsStockException $e) {
            $this->dispatch('toast', variant: 'error', message: $e->getMessage());

            return;
        }

        unset($this->cart, $this->subtotal);
        $this->dispatch('cart-updated');
    }

    public function removeItem(int $variantId): void
    {
        $variant = ProductVariant::query()->findOrFail($variantId);

        RemoveItemFromCart::run($this->cart, $variant);

        unset($this->cart, $this->subtotal);
        $this->dispatch('cart-updated');
        $this->dispatch('toast', variant: 'success', message: 'Removed from cart.');
    }

    public function render(): View
    {
        return view('livewire.storefront.cart-page');
    }

    /**
     * Shown until this component's own follow-up request resolves — a
     * skeleton matching the real cart's line-item layout so there's no
     * layout shift when it swaps in.
     */
    public function placeholder(): View
    {
        return view('livewire.storefront.cart-page-placeholder');
    }
}
