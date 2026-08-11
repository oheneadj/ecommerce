<?php

/**
 * Navbar live search-as-you-type dropdown — typo-tolerant product
 * suggestions (see App\Actions\Catalog\SearchProducts), embedded directly
 * in the storefront layout (a plain Blade file, not another Livewire
 * component), matching how CartIndicator is embedded there.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Catalog\SearchProducts;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * @property-read Collection<int, Product> $suggestions
 */
#[Lazy]
class SearchAutosuggest extends Component
{
    private const MIN_QUERY_LENGTH = 2;

    private const MAX_SUGGESTIONS = 8;

    /**
     * Generous relative to real usage — `wire:model.live.debounce` only
     * fires once per pause in typing, not per keystroke, so a human
     * typing normally never gets close to this. High enough to only ever
     * catch a scripted flood hammering the search endpoint directly.
     */
    private const RATE_LIMIT_PER_MINUTE = 60;

    public string $query = '';

    public bool $open = false;

    /**
     * Typing-triggered suggestions — skipped below MIN_QUERY_LENGTH
     * (avoids scanning the whole catalog over a single keystroke) and
     * rate-limited per visitor so a scripted flood of rapid-fire searches
     * can't hammer the database; a rate-limited request just returns no
     * new suggestions rather than erroring the page.
     */
    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function suggestions(): Collection
    {
        $term = trim($this->query);

        if (mb_strlen($term) < self::MIN_QUERY_LENGTH) {
            return new Collection;
        }

        $key = 'product-search:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_PER_MINUTE)) {
            return new Collection;
        }

        RateLimiter::hit($key, 60);

        return SearchProducts::run($term, self::MAX_SUGGESTIONS);
    }

    /**
     * Opens the dropdown as soon as there's something to show a result
     * (or a "no results") for — closed again via `close()`.
     */
    public function updatedQuery(): void
    {
        $this->open = trim($this->query) !== '';
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function render(): View
    {
        return view('livewire.storefront.search-autosuggest');
    }

    /**
     * Shown until this component's own follow-up request resolves —
     * matches the real search box's exact markup (same classes,
     * placeholder) so there's no layout shift when it swaps in.
     */
    public function placeholder(): View
    {
        return view('livewire.storefront.search-autosuggest-placeholder');
    }
}
