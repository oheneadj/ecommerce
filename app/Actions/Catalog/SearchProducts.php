<?php

/**
 * Fuzzy, typo-tolerant product search — no dedicated search engine
 * (Meilisearch/Typesense/Algolia are off the table on shared hosting), so
 * this works on plain Eloquent against either SQLite (dev) or MySQL
 * (production — see docs/infrastructure-deployment.md).
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Matches products by name, tolerating small typos (e.g. "nke" -> "Nike")
 * via per-word Levenshtein distance. Exact/substring matches always rank
 * first (score 0); fuzzy matches fill in afterward, closest first.
 *
 * Scans a capped batch of active, purchasable products in PHP rather than
 * a database-level fuzzy query — SQLite has no such operator, and this
 * must behave identically in dev (SQLite) and production (MySQL). Fine for
 * a catalog of a few hundred products; revisit (a real search index) if
 * the catalog grows into the thousands, since cost scales with
 * self::CANDIDATE_LIMIT regardless of how selective the search term is.
 */
class SearchProducts
{
    use AsAction;

    /**
     * Products scanned per search — keeps every search a single, bounded
     * query plus a fixed amount of PHP-side scoring work, regardless of
     * how large the catalog grows.
     */
    public const CANDIDATE_LIMIT = 300;

    /**
     * Longest search term considered — guards against a pathologically
     * long input driving up the cost of the per-word Levenshtein scoring
     * below (cost scales with the compared strings' lengths), which would
     * otherwise be an easy way to make every keystroke expensive.
     */
    private const MAX_TERM_LENGTH = 100;

    /**
     * Scores and ranks every active, purchasable product against `$term`,
     * returning the best `$limit` matches (see `score()` for how ranking
     * works).
     *
     * @return Collection<int, Product>
     */
    public function handle(string $term, int $limit = 8): Collection
    {
        $term = mb_substr(trim($term), 0, self::MAX_TERM_LENGTH);

        if ($term === '') {
            return new Collection;
        }

        $candidates = Product::query()
            ->where('status', ProductStatus::Active)
            ->whereHas('variants', fn ($query) => $query->where('status', VariantStatus::Active)->where('stock', '>', 0))
            ->with([
                'images',
                'variants' => fn ($query) => $query->where('status', VariantStatus::Active)->orderBy('price'),
                'variants.images',
            ])
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $scored = [];

        foreach ($candidates as $product) {
            $score = $this->score($product->name, $term);

            if ($score !== null) {
                $scored[] = [$score, $product];
            }
        }

        usort($scored, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return new Collection(
            array_map(fn (array $entry): Product => $entry[1], array_slice($scored, 0, $limit))
        );
    }

    /**
     * Lower is better. `0` for an exact substring match (correct spelling
     * always wins); otherwise the smallest Levenshtein distance between
     * the search term and any individual word in the product name,
     * offset so it always ranks behind substring matches. `null` means
     * the name is too different to be a plausible typo of the term at
     * all, so it's excluded entirely.
     */
    private function score(string $name, string $term): ?int
    {
        if (stripos($name, $term) !== false) {
            return 0;
        }

        $term = mb_strtolower($term);
        $tolerance = max(1, (int) floor(mb_strlen($term) / 3));
        $best = null;

        $words = preg_split('/\s+/', mb_strtolower($name)) ?: [];

        foreach ($words as $word) {
            // levenshtein() silently returns -1 (not an error) for inputs
            // over 255 bytes — guard against a pathological single "word"
            // (no spaces) in a product name skewing the score unnoticed.
            $distance = levenshtein($term, mb_substr($word, 0, 255));

            if ($distance <= $tolerance && ($best === null || $distance < $best)) {
                $best = $distance;
            }
        }

        return $best === null ? null : $best + 1;
    }
}
