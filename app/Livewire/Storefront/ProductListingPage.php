<?php

/**
 * Public product listing/search page — reactive search, category, brand,
 * and price filters, no full page reload.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Catalog\SearchProducts;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, Product> $products
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Brand> $brands
 * @property-read Collection<int, Attribute> $filterableAttributes
 * @property-read array<int, array<int, int>> $availableTermIdsByAttribute
 * @property-read array<int, int> $matchingProductIds
 * @property-read float $catalogMaxPrice
 */
#[Title('Shop')]
class ProductListingPage extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $category = null;

    #[Url]
    public ?string $brand = null;

    #[Url]
    public ?float $minPrice = null;

    #[Url]
    public ?float $maxPrice = null;

    /**
     * Selected attribute-term filters: attribute id => array of term ids.
     * Multiple terms within one attribute are OR'd (Red or Blue); multiple
     * attributes are AND'd together, requiring a single variant to match
     * all of them at once (a Red-AND-M product, not just any Red product
     * plus any M product) — see `applyAttributeFilters()`.
     *
     * @var array<int, array<int, int>>
     */
    #[Url(as: 'attrs')]
    public array $attributeFilters = [];

    /**
     * IDs of products matching the current search term (fuzzy/typo-
     * tolerant, see SearchProducts) — memoized per request via #[Computed]
     * so `baseProductQuery()` can be called repeatedly (once for the
     * listing, once per attribute in the facet computation below) without
     * re-running the search's own query/scoring work each time.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function matchingProductIds(): array
    {
        if ($this->search === '') {
            return [];
        }

        return SearchProducts::run($this->search, SearchProducts::CANDIDATE_LIMIT)->pluck('id')->all();
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'category', 'brand', 'minPrice', 'maxPrice'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return $this->applyAttributeFilters($this->baseProductQuery())
            ->with([
                'images',
                'variants' => fn ($query) => $query->where('status', VariantStatus::Active)->orderBy('price'),
                'variants.images',
            ])
            ->latest()
            ->paginate(12);
    }

    /**
     * The search/category/brand/price filter chain — everything except
     * pagination and the attribute-term filters, which are applied
     * separately by `applyAttributeFilters()` since the facet computation
     * below needs to apply them selectively (excluding one attribute at a
     * time) rather than all together.
     *
     * @return Builder<Product>
     */
    private function baseProductQuery(): Builder
    {
        return Product::query()
            ->where('status', ProductStatus::Active)
            ->when($this->search !== '', fn ($query) => $query->whereIn('id', $this->matchingProductIds))
            ->when($this->category, fn ($query) => $query->whereHas('category', fn ($query) => $query->where('slug', $this->category)))
            ->when($this->brand, fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('slug', $this->brand)))
            ->when($this->minPrice !== null, fn ($query) => $query->whereHas('variants', fn ($query) => $query->where('price', '>=', (int) round($this->minPrice * 100))))
            ->when($this->maxPrice !== null, fn ($query) => $query->whereHas('variants', fn ($query) => $query->where('price', '<=', (int) round($this->maxPrice * 100))));
    }

    /**
     * Requires an active, in-stock variant — and, for every selected
     * attribute (other than `$excludingAttributeId`, used when computing
     * that attribute's own available terms), that the *same* variant also
     * carries one of the selected terms. Chaining multiple
     * `whereHas('attributeTerms', ...)` calls on the same variant
     * subquery is what enforces "one variant matching every selected
     * attribute together" — each call constrains the same variant row, so
     * it must satisfy all of them at once, not just each independently.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applyAttributeFilters(Builder $query, ?int $excludingAttributeId = null): Builder
    {
        return $query->whereHas('variants', function (Builder $query) use ($excludingAttributeId): void {
            $query->where('status', VariantStatus::Active)->where('stock', '>', 0);

            foreach ($this->attributeFilters as $attributeId => $termIds) {
                if ((int) $attributeId === $excludingAttributeId || $termIds === []) {
                    continue;
                }

                $query->whereHas('attributeTerms', fn ($query) => $query->whereIn('attribute_terms.id', $termIds));
            }
        });
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Brand>
     */
    #[Computed]
    public function brands(): Collection
    {
        return Brand::query()->orderBy('name')->get();
    }

    /**
     * Upper bound for the price range slider — the highest price any
     * active, in-stock variant is actually selling for right now, in
     * major units (GH₵), rounded up so the slider's ceiling never sits
     * mid-price and cuts off the single most expensive item. Falls back
     * to a sane default when the catalog is empty so the slider still
     * renders something usable.
     */
    #[Computed]
    public function catalogMaxPrice(): float
    {
        $maxMinorUnits = ProductVariant::query()
            ->where('status', VariantStatus::Active)
            ->where('stock', '>', 0)
            ->max('price');

        if ($maxMinorUnits === null) {
            return 1000.0;
        }

        return (float) ceil($maxMinorUnits / 100);
    }

    /**
     * Every attribute with at least one term genuinely reachable somewhere
     * in the active/in-stock catalog — unconstrained by the CURRENT
     * filters (that's `availableTermIdsByAttribute()`'s job), just
     * whether the term is ever real at all. A term that's merely
     * narrowed-out by the current filters still shows here (greyed, via
     * `availableTermIdsByAttribute()`, for discoverability); a term no
     * variant has ever carried doesn't — there's nothing to discover.
     *
     * @return Collection<int, Attribute>
     */
    #[Computed]
    public function filterableAttributes(): Collection
    {
        $usedTermIds = AttributeTerm::query()
            ->whereHas('productVariants', fn ($query) => $query->where('status', VariantStatus::Active)
                ->where('stock', '>', 0)
                ->whereHas('product', fn ($query) => $query->where('status', ProductStatus::Active)))
            ->pluck('id');

        return Attribute::with(['terms' => fn ($query) => $query->orderBy('value')])
            ->get()
            ->map(function (Attribute $attribute) use ($usedTermIds): Attribute {
                $attribute = clone $attribute;
                $attribute->setRelation('terms', $attribute->terms->whereIn('id', $usedTermIds)->values());

                return $attribute;
            })
            ->filter(fn (Attribute $attribute) => $attribute->terms->isNotEmpty())
            ->values();
    }

    /**
     * For every attribute, which of its terms are reachable given
     * whatever else is currently filtered (search/category/brand/price,
     * and every OTHER selected attribute) — the dynamic-narrowing piece.
     * An attribute's own selection is excluded from its own computation
     * so picking a term doesn't immediately make itself disappear.
     *
     * @return array<int, array<int, int>>
     */
    #[Computed]
    public function availableTermIdsByAttribute(): array
    {
        $available = [];

        foreach ($this->filterableAttributes as $attribute) {
            $matchingProductIds = $this->applyAttributeFilters($this->baseProductQuery(), excludingAttributeId: $attribute->id)
                ->select('products.id');

            $available[$attribute->id] = AttributeTerm::query()
                ->where('attribute_id', $attribute->id)
                ->whereHas('productVariants', fn ($query) => $query->where('status', VariantStatus::Active)
                    ->where('stock', '>', 0)
                    ->whereHas('product', fn ($query) => $query->whereIn('id', $matchingProductIds)))
                ->pluck('id')
                ->all();
        }

        return $available;
    }

    public function toggleAttributeTerm(int $attributeId, int $termId): void
    {
        $selected = $this->attributeFilters[$attributeId] ?? [];

        if (in_array($termId, $selected, true)) {
            $selected = array_values(array_diff($selected, [$termId]));
        } else {
            $selected[] = $termId;
        }

        if ($selected === []) {
            unset($this->attributeFilters[$attributeId]);
        } else {
            $this->attributeFilters[$attributeId] = $selected;
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->attributeFilters = [];
        $this->reset('search', 'category', 'brand', 'minPrice', 'maxPrice');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.storefront.product-listing-page');
    }
}
