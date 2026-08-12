<?php

/**
 * A heart-icon wishlist toggle, embeddable inside static Blade contexts
 * (e.g. product-card, used both inside a Livewire-rendered page and the
 * plain HomeController-rendered homepage) — mirrors ProductDetailPage's
 * own wishlist toggle behavior exactly.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Wishlist\AddToWishlist;
use App\Actions\Wishlist\RemoveFromWishlist;
use App\Models\ProductVariant;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read bool $isWishlisted
 */
class WishlistButton extends Component
{
    public ProductVariant $variant;

    #[Computed]
    public function isWishlisted(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return WishlistItem::query()
            ->where('user_id', Auth::id())
            ->where('product_variant_id', $this->variant->id)
            ->exists();
    }

    /**
     * Guests get sent to login rather than silently failing — the same
     * behavior ProductDetailPage::toggleWishlist() already uses, since
     * wishlists are registered-users-only (BRD FR-8.1).
     */
    public function toggle(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login.phone', navigate: true);

            return;
        }

        if ($this->isWishlisted) {
            RemoveFromWishlist::run(Auth::user(), $this->variant);
            $this->dispatch('toast', variant: 'success', message: 'Removed from wishlist.');
        } else {
            AddToWishlist::run(Auth::user(), $this->variant);
            $this->dispatch('toast', variant: 'success', message: 'Added to wishlist.');
        }

        unset($this->isWishlisted);
    }

    public function render(): View
    {
        return view('livewire.storefront.wishlist-button');
    }
}
