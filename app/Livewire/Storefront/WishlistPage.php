<?php

/**
 * Customer-facing wishlist — view saved variants, add them to the cart, or
 * remove them.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\GetCurrentCart;
use App\Actions\Wishlist\RemoveFromWishlist;
use App\Exceptions\CartQuantityExceedsStockException;
use App\Models\ProductVariant;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, WishlistItem> $items
 */
#[Title('My Wishlist')]
class WishlistPage extends Component
{
    /**
     * @return Collection<int, WishlistItem>
     */
    #[Computed]
    public function items(): Collection
    {
        // A variant an admin discontinues (soft-deleted) is never cleaned
        // out of another customer's wishlist that already references it —
        // productVariant() then silently resolves to null (excluded by
        // the default soft-delete scope), which the view dereferences
        // unguarded, crashing this page entirely. Pruned here before
        // every render rather than left to crash.
        Auth::user()->wishlistItems()->whereDoesntHave('productVariant')->delete();

        return Auth::user()->wishlistItems()
            ->with(['productVariant.product', 'productVariant.images', 'productVariant.product.images'])
            ->latest('id')
            ->get();
    }

    public function removeItem(int $variantId): void
    {
        $variant = ProductVariant::query()->findOrFail($variantId);
        RemoveFromWishlist::run(Auth::user(), $variant);
        unset($this->items);
    }

    public function addToCart(int $variantId): void
    {
        $variant = ProductVariant::query()->findOrFail($variantId);
        $cart = GetCurrentCart::run(Auth::user());

        try {
            AddItemToCart::run($cart, $variant, 1);
        } catch (CartQuantityExceedsStockException $e) {
            $this->dispatch('toast', variant: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('cart-item-added');
        $this->dispatch('toast', variant: 'success', message: 'Added to cart.');
    }

    public function render(): View
    {
        return view('livewire.storefront.wishlist-page');
    }
}
