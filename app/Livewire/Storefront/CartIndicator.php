<?php

/**
 * The header cart icon — item count badge plus a click-to-open preview of
 * the cart's contents. Lives in the storefront layout on every page, and
 * refreshes itself whenever anything dispatches a `cart-updated` event
 * (add/update/remove, from any other component on the page).
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Cart\ResolveCurrentCart;
use App\Models\Cart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Cart|null $cart
 * @property-read int $itemCount
 * @property-read int $subtotal
 */
class CartIndicator extends Component
{
    public bool $open = false;

    /**
     * A non-creating lookup — unlike ResolveCurrentCart, the header
     * indicator must never create a cart row just because a page rendered;
     * that would leave an empty cart behind for every anonymous visit.
     */
    #[Computed]
    public function cart(): ?Cart
    {
        $query = Auth::check()
            ? Cart::query()->where('user_id', Auth::id())
            : Cart::query()->where('session_id', ResolveCurrentCart::guestSessionId())->whereNull('user_id');

        $cart = $query->whereDoesntHave('order')->latest('id')->first();

        $cart?->load(['items.productVariant.product', 'items.productVariant.images', 'items.productVariant.product.images']);

        return $cart;
    }

    #[Computed]
    public function itemCount(): int
    {
        return (int) ($this->cart?->items->sum('quantity') ?? 0);
    }

    #[Computed]
    public function subtotal(): int
    {
        return (int) ($this->cart?->items->sum(fn ($item) => $item->productVariant->price * $item->quantity) ?? 0);
    }

    #[On('cart-updated')]
    public function refreshCart(): void
    {
        unset($this->cart, $this->itemCount, $this->subtotal);
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function render(): View
    {
        return view('livewire.storefront.cart-indicator');
    }
}
