<?php

/**
 * Serves this deployment's brand colors as a real, cacheable CSS file.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * A dynamic route rather than a Vite-compiled stylesheet, deliberately —
 * an admin changing a brand color in Store Settings must take effect
 * immediately, with no rebuild/redeploy (Epic E13.2). Still a real CSS
 * file (not an inline `<style>` block) per CLAUDE.md §11's "no inline
 * style" rule — the cache is invalidated by StoreSetting's own `saved`
 * model event, not a TTL, so this never serves a stale color.
 */
class ThemeCssController extends Controller
{
    public const CACHE_KEY = 'theme.css';

    public function __invoke(): Response
    {
        $css = Cache::rememberForever(self::CACHE_KEY, function (): string {
            $store = StoreSetting::current();

            return ":root {\n"
                .'    --color-brand-primary: '.($store->primary_color ?? 'var(--color-zinc-900)').";\n"
                .'    --color-brand-secondary: '.($store->secondary_color ?? 'var(--color-zinc-600)').";\n"
                ."}\n";
        });

        return response($css, 200, ['Content-Type' => 'text/css; charset=UTF-8']);
    }
}
