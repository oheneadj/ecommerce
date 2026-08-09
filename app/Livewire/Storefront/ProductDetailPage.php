<?php

/**
 * Public product detail page — images, variant selector, reactive
 * price/stock, add to cart/wishlist, and approved reviews.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\GetCurrentCart;
use App\Actions\Wishlist\AddToWishlist;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read ProductVariant|null $selectedVariant
 * @property-read Collection<int, Review> $reviews
 * @property-read float $averageRating
 */
class ProductDetailPage extends Component
{
    public Product $product;

    /** @var array<int, int> */
    public array $selectedTermIds = [];

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
                'variants.images',
                'reviews' => fn ($query) => $query->where('status', ReviewStatus::Approved)->with('user')->latest(),
            ])
            ->firstOrFail();
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        if ($this->selectedTermIds === []) {
            return $this->product->variants->first();
        }

        $selected = collect($this->selectedTermIds)->values()->sort()->values()->all();

        return $this->product->variants->first(function (ProductVariant $variant) use ($selected): bool {
            $variantTermIds = $variant->attributeTerms->pluck('id')->sort()->values()->all();

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

    public function selectTerm(int $attributeId, int $termId): void
    {
        $this->selectedTermIds[$attributeId] = $termId;
    }

    public function addToCart(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login.phone', navigate: true);

            return;
        }

        $variant = $this->selectedVariant;

        if ($variant === null || $variant->stock <= 0) {
            return;
        }

        $cart = GetCurrentCart::run(Auth::user());
        AddItemToCart::run($cart, $variant, 1);
        $this->dispatch('cart-updated');
        $this->dispatch('toast', variant: 'success', message: 'Added to cart.');
    }

    public function addToWishlist(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login.phone', navigate: true);

            return;
        }

        $variant = $this->selectedVariant;

        if ($variant === null) {
            return;
        }

        AddToWishlist::run(Auth::user(), $variant);
        $this->dispatch('toast', variant: 'success', message: 'Added to wishlist.');
    }

    public function render(): View
    {
        return view('livewire.storefront.product-detail-page');
    }
}
