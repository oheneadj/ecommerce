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
        AddItemToCart::run($cart, $variant, 1);
        $this->dispatch('toast', variant: 'success', message: 'Added to cart.');
    }

    public function render(): View
    {
        return view('livewire.storefront.wishlist-page');
    }
}
