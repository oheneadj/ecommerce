<?php

/**
 * Public product detail page — images, variant selector, reactive
 * price/stock, add to cart/wishlist, and approved reviews.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\ResolveCurrentCart;
use App\Actions\Wishlist\AddToWishlist;
use App\Actions\Wishlist\RemoveFromWishlist;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Enums\VariantStatus;
use App\Exceptions\CartQuantityExceedsStockException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read ProductVariant|null $selectedVariant
 * @property-read Collection<int, Review> $reviews
 * @property-read float $averageRating
 * @property-read bool $isWishlisted
 */
class ProductDetailPage extends Component
{
    public Product $product;

    /**
     * Reflected in the URL (?options[attributeId]=termId) so a reload, or
     * sharing the link, keeps whichever combination the customer picked
     * instead of silently reverting to the default variant.
     *
     * @var array<int, int>
     */
    #[Url(as: 'options')]
    public array $selectedTermIds = [];

    /**
     * Set only when a variant is picked directly (the fallback list shown
     * for products with no global Attribute selector — see
     * `hasAttributeSelector`). Takes precedence over `$selectedTermIds`.
     * Also reflected in the URL (?variant=id) for the same reason.
     */
    #[Url(as: 'variant')]
    public ?int $selectedVariantId = null;

    public function mount(string $productSlug): void
    {
        $this->product = Product::query()
            ->where('status', ProductStatus::Active)
            ->where('slug', $productSlug)
            ->with([
                'images',
                'category',
                'brand',
                'attributes.terms',
                'variants' => fn ($query) => $query->where('status', VariantStatus::Active)->orderBy('price'),
                'variants.attributeTerms',
                'variants.attributeValues',
                'variants.images',
                'reviews' => fn ($query) => $query->where('status', ReviewStatus::Approved)->with('user')->latest(),
            ])
            ->firstOrFail();

        // When the product uses the global attribute selector, a variant
        // with no attribute term set at all is incomplete catalog data —
        // it can never be reached through the selector (its combination
        // matches nothing) and would only ever surface as an unlabelled
        // default. Drop it rather than let it silently show/sell.
        if ($this->product->attributes->isNotEmpty()) {
            $this->product->setRelation(
                'variants',
                $this->product->variants->filter(fn (ProductVariant $variant): bool => $variant->attributeTerms->isNotEmpty())->values(),
            );
        }
    }

    /**
     * True when the product has variants to choose between but none of
     * them differ by a global Attribute/AttributeTerm — e.g. variants
     * created by SKU/price alone, or via custom per-variant attribute
     * values only. The attribute-term button groups below have nothing to
     * render in that case, so the view falls back to a plain per-variant
     * list instead — otherwise every variant past the first is
     * permanently unreachable on the storefront.
     */
    #[Computed]
    public function hasAttributeSelector(): bool
    {
        return $this->product->attributes->isNotEmpty();
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        if ($this->selectedVariantId !== null) {
            $direct = $this->product->variants->firstWhere('id', $this->selectedVariantId);

            if ($direct !== null) {
                return $direct;
            }
        }

        if ($this->selectedTermIds === []) {
            return $this->product->variants->first();
        }

        // Cast to int explicitly — values restored from the URL query
        // string (a page reload/shared link) come back as strings, which
        // would otherwise never strictly-equal the integer ids from
        // attributeTerms->pluck('id') below and silently fall through to
        // the default variant.
        $selected = collect($this->selectedTermIds)->map(fn ($id): int => (int) $id)->values()->sort()->values()->all();

        return $this->product->variants->first(function (ProductVariant $variant) use ($selected): bool {
            $variantTermIds = $variant->attributeTerms->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

            return $variantTermIds === $selected;
        }) ?? $this->product->variants->first();
    }

    /**
     * @return Collection<int, Review>
     */
    #[Computed]
    public function reviews(): Collection
    {
        return $this->product->reviews;
    }

    #[Computed]
    public function averageRating(): float
    {
        return round((float) $this->reviews->avg('rating'), 1);
    }

    #[Computed]
    public function isWishlisted(): bool
    {
        if (! Auth::check() || $this->selectedVariant === null) {
            return false;
        }

        return WishlistItem::query()
            ->where('user_id', Auth::id())
            ->where('product_variant_id', $this->selectedVariant->id)
            ->exists();
    }

    public function selectTerm(int $attributeId, int $termId): void
    {
        $this->selectedVariantId = null;
        $this->selectedTermIds[$attributeId] = $termId;
    }

    /**
     * Direct variant pick, used by the fallback list for products with no
     * global Attribute selector (see `hasAttributeSelector`).
     */
    public function selectVariant(int $variantId): void
    {
        $this->selectedTermIds = [];
        $this->selectedVariantId = $variantId;
    }

    public function addToCart(): void
    {
        $variant = $this->selectedVariant;

        if ($variant === null || $variant->stock <= 0) {
            return;
        }

        $cart = ResolveCurrentCart::run(Auth::user(), ResolveCurrentCart::guestSessionId());

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

    public function toggleWishlist(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login.phone', navigate: true);

            return;
        }

        $variant = $this->selectedVariant;

        if ($variant === null) {
            return;
        }

        if ($this->isWishlisted) {
            RemoveFromWishlist::run(Auth::user(), $variant);
            $this->dispatch('toast', variant: 'success', message: 'Removed from wishlist.');
        } else {
            AddToWishlist::run(Auth::user(), $variant);
            $this->dispatch('toast', variant: 'success', message: 'Added to wishlist.');
        }

        unset($this->isWishlisted);
    }

    public function render(): View
    {
        return view('livewire.storefront.product-detail-page');
    }
}
