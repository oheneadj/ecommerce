<?php

/**
 * Singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use App\Enums\PaymentProvider;
use App\Enums\SmsProvider;
use App\Http\Controllers\Storefront\ThemeCssController;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property string|null $x_url
 * @property string|null $tiktok_url
 * @property string|null $whatsapp_url
 * @property PaymentProvider|null $active_payment_provider
 * @property SmsProvider|null $active_sms_provider
 * @property int $tax_rate
 * @property int $stock_reservation_minutes
 * @property int $low_stock_threshold
 * @property Carbon|null $health_alerts_snoozed_until
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
    'facebook_url',
    'instagram_url',
    'x_url',
    'tiktok_url',
    'whatsapp_url',
    'active_payment_provider',
    'active_sms_provider',
    'tax_rate',
    'stock_reservation_minutes',
    'low_stock_threshold',
    'health_alerts_snoozed_until',
])]
class StoreSetting extends Model
{
    use LogsAdminActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'health_alerts_snoozed_until' => 'datetime',
            'active_payment_provider' => PaymentProvider::class,
            'active_sms_provider' => SmsProvider::class,
        ];
    }

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

    /**
     * Only the social platforms actually set, keyed by the icon name
     * `<x-app-icon>` already knows (same names used for the product-share
     * icons) — keeps the storefront footer a plain loop instead of one
     * `@if` per platform.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        return array_filter([
            'facebook' => $this->facebook_url,
            'instagram' => $this->instagram_url,
            'x' => $this->x_url,
            'tiktok' => $this->tiktok_url,
            'whatsapp' => $this->whatsapp_url,
        ]);
    }
}
