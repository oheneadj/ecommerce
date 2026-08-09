<?php

/**
 * Tier 1 (config/schema) — WARNING: asserts the standard legal/info pages exist and have content.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use App\Models\StaticPage;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Every expected slug must exist, be published, and have real content —
 * this app has no seeded placeholder text to pattern-match against, so
 * "real content" is judged by a minimum length rather than string-matching
 * a specific placeholder (there's nothing generic to match against).
 */
class StaticPagesHaveContent extends Check
{
    /** @var array<int, string> */
    private const EXPECTED_SLUGS = ['about', 'contact', 'terms', 'privacy-policy', 'refund-policy'];

    private const MIN_CONTENT_LENGTH = 40;

    public function run(): Result
    {
        $result = Result::make();

        $pages = StaticPage::query()->whereIn('slug', self::EXPECTED_SLUGS)->get()->keyBy('slug');

        $problems = collect(self::EXPECTED_SLUGS)->map(function (string $slug) use ($pages): ?string {
            $page = $pages->get($slug);

            if ($page === null) {
                return "{$slug} (missing)";
            }

            if (! $page->is_published) {
                return "{$slug} (unpublished)";
            }

            if (mb_strlen((string) $page->content) < self::MIN_CONTENT_LENGTH) {
                return "{$slug} (little to no content)";
            }

            return null;
        })->filter()->values();

        if ($problems->isEmpty()) {
            return $result->ok('Every standard static page exists, is published, and has content.');
        }

        return $result
            ->warning('These static pages need attention: '.$problems->implode(', ').'. Fix: create/publish/fill them in via the Static Pages resource in the admin panel.')
            ->meta(['problem_pages' => $problems->all()]);
    }
}
