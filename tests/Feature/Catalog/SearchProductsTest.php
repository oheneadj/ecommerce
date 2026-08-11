<?php

/**
 * Covers App\Actions\Catalog\SearchProducts — fuzzy, typo-tolerant product
 * search (e.g. "nke" still finds "Nike").
 */

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SearchProducts;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchProductsTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableProduct(string $name): Product
    {
        $product = Product::factory()->create(['name' => $name, 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        return $product;
    }

    public function test_an_exact_substring_match_is_found(): void
    {
        $nike = $this->purchasableProduct('Nike Air Max');

        $results = SearchProducts::run('Nike');

        $this->assertTrue($results->contains($nike));
    }

    public function test_a_typo_still_finds_the_matching_product(): void
    {
        $nike = $this->purchasableProduct('Nike Air Max');

        $results = SearchProducts::run('nke');

        $this->assertTrue($results->contains($nike));
    }

    public function test_exact_matches_rank_before_fuzzy_matches(): void
    {
        // "Nike" is 1 edit from "nke"; "Nikke" (a made-up unrelated brand)
        // contains "nke"... actually doesn't — use a genuine exact
        // substring vs. a typo'd one to prove ordering.
        $exact = $this->purchasableProduct('Nke Brand Socks');
        $typo = $this->purchasableProduct('Nike Air Max');

        $results = SearchProducts::run('nke');

        $this->assertSame($exact->id, $results->first()?->id);
        $this->assertTrue($results->contains($typo));
    }

    public function test_an_unrelated_term_finds_nothing(): void
    {
        $this->purchasableProduct('Nike Air Max');

        $results = SearchProducts::run('refrigerator');

        $this->assertTrue($results->isEmpty());
    }

    public function test_a_blank_term_returns_no_results(): void
    {
        $this->purchasableProduct('Nike Air Max');

        $this->assertTrue(SearchProducts::run('')->isEmpty());
        $this->assertTrue(SearchProducts::run('   ')->isEmpty());
    }

    public function test_a_draft_product_is_never_matched(): void
    {
        $draft = Product::factory()->create(['name' => 'Nike Air Max', 'status' => ProductStatus::Draft]);
        ProductVariant::factory()->create(['product_id' => $draft->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->assertTrue(SearchProducts::run('Nike')->isEmpty());
    }

    public function test_a_product_with_no_stock_is_never_matched(): void
    {
        $outOfStock = Product::factory()->create(['name' => 'Nike Air Max', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $outOfStock->id, 'status' => VariantStatus::Active, 'stock' => 0]);

        $this->assertTrue(SearchProducts::run('Nike')->isEmpty());
    }

    public function test_the_result_count_respects_the_limit(): void
    {
        foreach (range(1, 5) as $i) {
            $this->purchasableProduct("Nike Shoe {$i}");
        }

        $results = SearchProducts::run('Nike', limit: 2);

        $this->assertCount(2, $results);
    }

    public function test_a_pathologically_long_term_does_not_error(): void
    {
        $this->purchasableProduct('Nike Air Max');

        $results = SearchProducts::run(str_repeat('a', 5000));

        $this->assertTrue($results->isEmpty());
    }
}
