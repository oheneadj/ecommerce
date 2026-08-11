<?php

/**
 * Covers App\Livewire\Storefront\SearchAutosuggest — the navbar's live
 * search-as-you-type dropdown.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Livewire\Storefront\SearchAutosuggest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SearchAutosuggestTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableProduct(string $name): Product
    {
        $product = Product::factory()->create(['name' => $name, 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        return $product;
    }

    public function test_typing_a_typo_still_surfaces_a_matching_suggestion(): void
    {
        $nike = $this->purchasableProduct('Nike Air Max');

        Livewire::test(SearchAutosuggest::class)
            ->set('query', 'nke')
            ->assertSet('open', true)
            ->assertSee($nike->name);
    }

    public function test_a_single_character_shows_no_suggestions_yet(): void
    {
        $this->purchasableProduct('Nike Air Max');

        Livewire::test(SearchAutosuggest::class)
            ->set('query', 'n')
            ->assertDontSee('Nike Air Max');
    }

    public function test_clearing_the_query_closes_the_dropdown(): void
    {
        $this->purchasableProduct('Nike Air Max');

        Livewire::test(SearchAutosuggest::class)
            ->set('query', 'Nike')
            ->assertSet('open', true)
            ->set('query', '')
            ->assertSet('open', false);
    }

    public function test_a_rate_limited_visitor_gets_no_new_suggestions(): void
    {
        $this->purchasableProduct('Nike Air Max');

        $key = 'product-search:127.0.0.1';
        RateLimiter::clear($key);

        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit($key, 60);
        }

        Livewire::test(SearchAutosuggest::class)
            ->set('query', 'Nike')
            ->assertDontSee('Nike Air Max');

        RateLimiter::clear($key);
    }
}
