<?php

/**
 * Public product listing/search page — reactive search, category, brand,
 * and price filters, no full page reload.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
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
        return Product::query()
            ->where('status', ProductStatus::Active)
            ->whereHas('variants', fn ($query) => $query->where('status', VariantStatus::Active)->where('stock', '>', 0))
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->category, fn ($query) => $query->whereHas('category', fn ($query) => $query->where('slug', $this->category)))
            ->when($this->brand, fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('slug', $this->brand)))
            ->when($this->minPrice !== null, fn ($query) => $query->whereHas('variants', fn ($query) => $query->where('price', '>=', (int) round($this->minPrice * 100))))
            ->when($this->maxPrice !== null, fn ($query) => $query->whereHas('variants', fn ($query) => $query->where('price', '<=', (int) round($this->maxPrice * 100))))
            ->with(['images', 'variants' => fn ($query) => $query->where('status', VariantStatus::Active)->orderBy('price')])
            ->latest()
            ->paginate(12);
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

    public function resetFilters(): void
    {
        $this->reset('search', 'category', 'brand', 'minPrice', 'maxPrice');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.storefront.product-listing-page');
    }
}
