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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read bool $isWishlisted
 */
class WishlistButton extends Component
{
    public ProductVariant $variant;

    /**
     * Every product card on a listing page embeds its own instance of
     * this component — with no cross-instance cache, each one ran its own
     * `wishlist_items` existence query, an N+1-shaped cost invisible to
     * `Model::preventLazyLoading()` since it's component composition, not
     * an Eloquent relation traversal. Memoized per request via the array
     * cache store (resolved fresh from the container each request — and
     * each test, unlike a raw static class property, which would leak
     * stale data across PHPUnit test methods run in the same process).
     *
     * @return Collection<int, int>
     */
    private static function wishlistedVariantIds(): Collection
    {
        $userId = Auth::id();

        return Cache::store('array')->rememberForever(
            "wishlist-button:wishlisted-variant-ids:{$userId}",
            fn () => WishlistItem::query()->where('user_id', $userId)->pluck('product_variant_id'),
        );
    }

    #[Computed]
    public function isWishlisted(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return self::wishlistedVariantIds()->contains($this->variant->id);
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

        Cache::store('array')->forget('wishlist-button:wishlisted-variant-ids:'.Auth::id());
        unset($this->isWishlisted);
    }

    public function render(): View
    {
        return view('livewire.storefront.wishlist-button');
    }
}
