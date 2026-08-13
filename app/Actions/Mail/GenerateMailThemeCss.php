<?php

/**
 * Regenerates the branded email theme CSS from the current Store Settings.
 */

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Laravel's Markdown mail theme is a raw `.css` file, not a Blade view — it
 * can't read `StoreSetting` directly the way the header/footer views do.
 * This reads an immutable *source* template (`default.source.css`, with a
 * `{{BRAND_PRIMARY}}` placeholder in place of the theme's original accent
 * color) and writes the substituted result to `default.css`, the file
 * Laravel's Markdown renderer actually loads.
 *
 * Deliberately generates from the untouched source every time, never from
 * `default.css` itself — regenerating from an already-substituted file
 * would find no `{{BRAND_PRIMARY}}` token left to replace on the second
 * run, silently leaving a stale color in place after a later brand-color
 * change.
 */
class GenerateMailThemeCss
{
    use AsAction;

    private const SOURCE_PATH = 'vendor/mail/html/themes/default.source.css';

    private const OUTPUT_PATH = 'vendor/mail/html/themes/default.css';

    public function handle(): void
    {
        $store = StoreSetting::current();
        $primaryColor = $store->primary_color ?: '#18181b';

        $source = File::get(resource_path('views/'.self::SOURCE_PATH));
        $css = str_replace('{{BRAND_PRIMARY}}', $primaryColor, $source);

        File::put(resource_path('views/'.self::OUTPUT_PATH), $css);
    }
}
