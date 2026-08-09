<?php

/**
 * Singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use App\Http\Controllers\Storefront\ThemeCssController;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Always exactly one row. Business-behavior configuration (reservation
 * window, branding, etc.) lives here so it's admin-editable without a
 * deploy — never hardcode these values in an Action. This is the one
 * thing that changes between deployments of this codebase for different
 * businesses (Epic E13): branding fields let the storefront/PDF receipts
 * be reskinned per business with no code change, and `tax_rate` is the
 * single-jurisdiction rate applied uniformly to every order's subtotal.
 *
 * @property int $id
 * @property string|null $business_name
 * @property string|null $logo_path
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property string|null $tagline
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $contact_address
 * @property int $tax_rate
 * @property int $stock_reservation_minutes
 * @property int $low_stock_threshold
 */
#[Fillable([
    'business_name',
    'logo_path',
    'primary_color',
    'secondary_color',
    'tagline',
    'contact_email',
    'contact_phone',
    'contact_address',
    'tax_rate',
    'stock_reservation_minutes',
    'low_stock_threshold',
])]
class StoreSetting extends Model
{
    use LogsAdminActivity;

    /**
     * /theme.css is cached forever, not on a TTL — it must never serve a
     * color an admin already changed, so the cache is cleared the instant
     * this row saves instead. The old logo file is deleted the same way,
     * whenever it's replaced or cleared, since this singleton row is
     * never itself deleted.
     */
    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(ThemeCssController::CACHE_KEY));

        static::saving(function (self $settings): void {
            if (! $settings->isDirty('logo_path')) {
                return;
            }

            $original = $settings->getOriginal('logo_path');

            if ($original) {
                Storage::disk('public')->delete($original);
            }
        });
    }

    /**
     * Get the single settings row, creating it with defaults if missing.
     *
     * `firstOrCreate` leaves DB-default column values unset on the
     * in-memory instance when it inserts a new row (Eloquent doesn't
     * re-read the row after insert), so a freshly created row is re-fetched
     * to guarantee every attribute reflects what's actually in the database.
     */
    public static function current(): self
    {
        $settings = self::query()->firstOrCreate([]);

        return $settings->wasRecentlyCreated ? ($settings->fresh() ?? $settings) : $settings;
    }
}
