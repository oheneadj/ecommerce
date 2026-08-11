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
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\StoreSetting;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, ProductVariant> $usableVariants
 * @property-read Collection<int, Attribute> $usableAttributes
 * @property-read array<int, array<int, int>> $availableTermIdsByAttribute
 * @property-read Collection<int, Attribute> $missingAttributes
 * @property-read bool $hasAttributeSelector
 * @property-read ProductVariant|null $selectedVariant
 * @property-read Collection<int, Review> $reviews
 * @property-read float $averageRating
 * @property-read bool $isWishlisted
 * @property-read string $shareText
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
            ->with($this->eagerLoads())
            ->firstOrFail();

        // The page must always load with a real variant selected (price/
        // stock visible, Add to cart usable) rather than silently picking
        // one internally while every Color/Size button looks unselected.
        // Only seed this on a genuinely fresh visit — a URL carrying its
        // own `?options[...]`/`?variant=` state (a reload or shared link)
        // must keep exactly what the customer/link specified, including a
        // deliberately partial selection.
        if ($this->usableAttributes->isNotEmpty() && $this->selectedTermIds === [] && $this->selectedVariantId === null) {
            $default = $this->usableVariants->first();

            if ($default !== null) {
                $this->selectedTermIds = $default->attributeTerms
                    ->mapWithKeys(fn ($term) => [$term->attribute_id => $term->id])
                    ->all();
            }
        }
    }

    /**
     * Livewire's Eloquent model/collection rehydration between requests
     * (every `wire:click` action call is a separate request) restores
     * `$this->product`'s own attributes and its top-level relations, but
     * NOT relations-of-relations nested inside a collection (e.g. each
     * `ProductVariant`'s own `attributeTerms`/`images`, or `Attribute`'s
     * `terms`) — those come back unloaded every time, silently re-queried
     * one at a time unless something catches it (exactly what
     * `Model::preventLazyLoading()` is for). `hydrate()` runs on every
     * request after the first (unlike `mount()`, which only runs once) —
     * the right place to restore anything `loadMissing` finds gone.
     */
    public function hydrate(): void
    {
        $this->product->loadMissing($this->eagerLoads());
    }

    /**
     * The full eager-load spec for `$this->product`, shared by `mount()`
     * (the initial query) and `hydrate()` (re-applied via `loadMissing()`
     * on every later request — see `hydrate()`'s own docblock for why).
     *
     * @return array<int|string, string|\Closure>
     */
    private function eagerLoads(): array
    {
        return [
            'images',
            'category.parent',
            'brand',
            'attributes.terms',
            'variants' => fn ($query) => $query->where('status', VariantStatus::Active)->orderBy('price'),
            'variants.attributeTerms',
            'variants.attributeValues',
            'variants.images',
            'reviews' => fn ($query) => $query->where('status', ReviewStatus::Approved)->with('user')->latest(),
        ];
    }

    /**
     * `$selectedTermIds` restored from the URL query string (a reload or
     * shared link) comes back with string values, which would never
     * strictly-equal the integer term ids read from `attributeTerms`
     * elsewhere in this class — normalize once and reuse everywhere
     * selections are compared against real data.
     *
     * @return array<int, int>
     */
    private function normalizedSelections(): array
    {
        return collect($this->selectedTermIds)->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * `$this->product`'s own `variants`/`attributes` relations are the raw
     * eager-loaded data as originally queried — filtering them via
     * `setRelation()` in `mount()` does NOT survive Livewire's
     * serialize/rehydrate cycle between separate requests (each
     * `wire:click` action call rehydrates the component from a fresh
     * snapshot, which re-derives relations from the snapshot's own record
     * of what was loaded, not from ad-hoc post-load mutations). These
     * `#[Computed]` properties recompute the same filtering fresh on
     * every request instead, so it's never stale.
     */

    /**
     * A variant with no attribute term set at all, on a product that uses
     * the global attribute selector, is incomplete catalog data — it can
     * never be reached through the selector (its combination matches
     * nothing) and would only ever surface as an unlabelled default.
     *
     * @return Collection<int, ProductVariant>
     */
    #[Computed]
    public function usableVariants(): Collection
    {
        // `variants.product` was never in mount()'s eager-load list —
        // pointless as a query anyway, since every one of these variants
        // belongs to this exact, already-loaded `$this->product`. Setting
        // the inverse relation directly avoids both the extra query AND a
        // lazy-load violation the moment `galleryImages()` (or anything
        // else) reads `$variant->product`.
        $this->product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $this->product));

        if ($this->product->attributes->isEmpty()) {
            return $this->product->variants;
        }

        return $this->product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->attributeTerms->isNotEmpty())
            ->values();
    }

    /**
     * `Attribute::terms()` lists every term ever created for that
     * attribute across the whole catalog (e.g. every color used by any
     * product), not just the ones this product's own variants carry — so
     * a term with no variant of this product attached to it (e.g. "Blue"
     * on a product that only ever stocked Green and White) would still
     * render as a clickable, but dead-ended, option. Filters each
     * attribute's terms down to only the ones at least one of this
     * product's (usable) variants actually uses, and drops the whole
     * attribute group if that leaves it with none.
     *
     * @return Collection<int, Attribute>
     */
    #[Computed]
    public function usableAttributes(): Collection
    {
        $usedTermIds = $this->usableVariants
            ->flatMap(fn (ProductVariant $variant) => $variant->attributeTerms->pluck('id'))
            ->unique()
            ->all();

        return $this->product->attributes
            ->map(function (Attribute $attribute) use ($usedTermIds): Attribute {
                $attribute = clone $attribute;
                $attribute->setRelation('terms', $attribute->terms->whereIn('id', $usedTermIds)->values());

                return $attribute;
            })
            ->filter(fn (Attribute $attribute) => $attribute->terms->isNotEmpty())
            ->values();
    }

    /**
     * Every variant of this product whose own selected terms match
     * `$selections` on the attributes present as keys — attributes absent
     * from `$selections` are unconstrained, so this doubles as "what's
     * possible so far" for a still-incomplete pick and "the one exact
     * match" for a complete one.
     *
     * @param  array<int, int>  $selections  attribute id => term id
     * @return Collection<int, ProductVariant>
     */
    private function variantsMatching(array $selections): Collection
    {
        return $this->usableVariants->filter(function (ProductVariant $variant) use ($selections): bool {
            $variantTermIdsByAttribute = $variant->attributeTerms->mapWithKeys(fn ($term) => [$term->attribute_id => $term->id]);

            foreach ($selections as $attributeId => $termId) {
                if (($variantTermIdsByAttribute[$attributeId] ?? null) !== $termId) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * For every attribute, which of its terms remain reachable given
     * whatever's currently selected on the OTHER attributes — used to
     * gray out combinations that don't exist (e.g. no Red in size 42)
     * before the customer clicks into a dead end.
     *
     * @return array<int, array<int, int>> attribute id => term ids
     */
    #[Computed]
    public function availableTermIdsByAttribute(): array
    {
        $available = [];

        foreach ($this->usableAttributes as $attribute) {
            $otherSelections = collect($this->normalizedSelections())->except([$attribute->id])->all();

            $available[$attribute->id] = $this->variantsMatching($otherSelections)
                ->flatMap(fn (ProductVariant $variant) => $variant->attributeTerms->where('attribute_id', $attribute->id)->pluck('id'))
                ->unique()
                ->values()
                ->all();
        }

        return $available;
    }

    /**
     * Attributes the customer hasn't picked a value for yet — while this
     * is non-empty, "no variant found" means "selection incomplete," not
     * "unavailable," and the two must be shown differently.
     *
     * @return Collection<int, Attribute>
     */
    #[Computed]
    public function missingAttributes(): Collection
    {
        return $this->usableAttributes->reject(
            fn ($attribute) => array_key_exists($attribute->id, $this->selectedTermIds),
        )->values();
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
        return $this->usableAttributes->isNotEmpty();
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        if ($this->selectedVariantId !== null) {
            $direct = $this->usableVariants->firstWhere('id', $this->selectedVariantId);

            if ($direct !== null) {
                return $direct;
            }
        }

        if (! $this->hasAttributeSelector) {
            return $this->usableVariants->first();
        }

        // Incomplete selection (e.g. Color picked, Size not yet) is not
        // the same thing as "this combination doesn't exist" — the view
        // shows a "pick a Size" prompt for this, not "Currently
        // unavailable" (see `missingAttributes`). No fallback to
        // `variants->first()` either way — silently substituting an
        // unrelated variant (e.g. the cheapest one, in a different color)
        // would misrepresent what's shown/added to cart.
        if ($this->missingAttributes->isNotEmpty()) {
            return null;
        }

        $selected = collect($this->normalizedSelections())->sort()->values()->all();

        return $this->usableVariants->first(function (ProductVariant $variant) use ($selected): bool {
            $variantTermIds = $variant->attributeTerms->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

            return $variantTermIds === $selected;
        });
    }

    /**
     * Share text for WhatsApp/X — the plain product name alone reads as a
     * bare link with no reason to click; naming the price and the store
     * gives a friend something to actually react to. Falls back to
     * "Check out {product}" with no price when nothing's selected/in
     * stock, same "don't imply availability that isn't there" rule the
     * rest of the page follows.
     */
    #[Computed]
    public function shareText(): string
    {
        $businessName = StoreSetting::current()->business_name ?: config('app.name', 'Laravel');
        $variant = $this->selectedVariant;

        if ($variant !== null && $variant->stock > 0) {
            return __(':product for :price at :store — check it out!', [
                'product' => $this->product->name,
                'price' => $variant->price_formatted,
                'store' => $businessName,
            ]);
        }

        return __('Check out :product at :store!', [
            'product' => $this->product->name,
            'store' => $businessName,
        ]);
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
        // Swatches stay clickable even when greyed out in the view (not
        // compatible with the current OTHER picks) — that's a visual
        // hint, not a lock, so switching Color away from a Size that only
        // the old Color had still works in one click rather than forcing
        // the customer to clear Size first. The only pick that's truly
        // rejected here is one that doesn't correspond to ANY variant of
        // this product at all — not reachable through the real UI
        // (`usableAttributes` already filters terms down to used-only
        // ones), so only a forced/synthetic call could hit it — recorded
        // as-is rather than cascading a prune that would blame an
        // unrelated, perfectly valid selection instead.
        $attribute = $this->usableAttributes->firstWhere('id', $attributeId);
        $isRealTermForThisProduct = $attribute !== null && $attribute->terms->contains('id', $termId);

        $this->selectedVariantId = null;
        $this->selectedTermIds[$attributeId] = $termId;

        // Picking a term can make an already-chosen value on ANOTHER
        // attribute unreachable (e.g. Size 42 doesn't exist in Red) —
        // drop it rather than leave the customer in a silently-impossible
        // combination. Single pass is enough for this app's actual
        // attribute count (Color + Size); a product with 3+ interacting
        // attributes would need this repeated to a fixed point.
        if ($isRealTermForThisProduct) {
            unset($this->availableTermIdsByAttribute);

            foreach ($this->usableAttributes as $otherAttribute) {
                if ($otherAttribute->id === $attributeId) {
                    continue;
                }

                $currentlySelected = $this->selectedTermIds[$otherAttribute->id] ?? null;

                if ($currentlySelected !== null && ! in_array((int) $currentlySelected, $this->availableTermIdsByAttribute[$otherAttribute->id] ?? [], true)) {
                    unset($this->selectedTermIds[$otherAttribute->id]);
                }
            }
        }

        unset($this->availableTermIdsByAttribute, $this->missingAttributes, $this->selectedVariant, $this->isWishlisted);
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
