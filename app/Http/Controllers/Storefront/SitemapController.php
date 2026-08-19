<?php

/**
 * Serves /sitemap.xml — the crawl-discovery signal the storefront had
 * none of before (see docs/technical-design-ecommerce.md §6a). Lists the
 * homepage, product listing, every active product, and every published
 * static page. Cart/checkout/account/wishlist are deliberately excluded —
 * they're also blocked in robots.txt and carry a noindex tag.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StaticPage;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('products.index'), 'changefreq' => 'daily', 'priority' => '0.9'],
        ];

        Product::query()
            ->where('status', ProductStatus::Active)
            ->orderBy('id')
            ->select(['slug', 'updated_at'])
            ->chunk(200, function ($products) use (&$urls): void {
                foreach ($products as $product) {
                    $urls[] = [
                        'loc' => route('products.show', $product->slug),
                        'lastmod' => $product->updated_at?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                }
            });

        StaticPage::query()
            ->where('is_published', true)
            ->orderBy('id')
            ->select(['slug', 'updated_at'])
            ->chunk(200, function ($pages) use (&$urls): void {
                foreach ($pages as $page) {
                    $urls[] = [
                        'loc' => route('pages.show', $page->slug),
                        'lastmod' => $page->updated_at?->toAtomString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.5',
                    ];
                }
            });

        return response()
            ->view('sitemap.index', ['urls' => $urls])
            ->header('Content-Type', 'text/xml');
    }
}
