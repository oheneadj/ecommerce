<?php

/**
 * Singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Models;

use App\Actions\Mail\GenerateMailThemeCss;
use App\Concerns\LogsAdminActivity;
use App\Enums\BackupFrequency;
use App\Enums\RemoteStorageProvider;
use App\Enums\SmsProvider;
use App\Http\Controllers\Storefront\ThemeCssController;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
 * @property string|null $address_street
 * @property string|null $address_city
 * @property string|null $address_region
 * @property string|null $address_postal_code
 * @property string $address_country
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $ga_measurement_id
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property string|null $x_url
 * @property string|null $tiktok_url
 * @property string|null $whatsapp_url
 * @property bool $whatsapp_chat_enabled
 * @property SmsProvider|null $active_sms_provider
 * @property RemoteStorageProvider|null $active_remote_storage_provider
 * @property bool $backup_auto_enabled
 * @property BackupFrequency|null $backup_frequency
 * @property int $backup_retention_days
 * @property int $tax_rate
 * @property string $timezone
 * @property int $stock_reservation_minutes
 * @property int $low_stock_threshold
 * @property Carbon|null $health_alerts_snoozed_until
 * @property array<int, string>|null $health_alerts_snoozed_failures
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
    'address_street',
    'address_city',
    'address_region',
    'address_postal_code',
    'address_country',
    'latitude',
    'longitude',
    'ga_measurement_id',
    'facebook_url',
    'instagram_url',
    'x_url',
    'tiktok_url',
    'whatsapp_url',
    'whatsapp_chat_enabled',
    'active_sms_provider',
    'active_remote_storage_provider',
    'backup_auto_enabled',
    'backup_frequency',
    'backup_retention_days',
    'tax_rate',
    'timezone',
    'stock_reservation_minutes',
    'low_stock_threshold',
    'health_alerts_snoozed_until',
    'health_alerts_snoozed_failures',
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
            'health_alerts_snoozed_failures' => 'array',
            'active_sms_provider' => SmsProvider::class,
            'whatsapp_chat_enabled' => 'boolean',
            'active_remote_storage_provider' => RemoteStorageProvider::class,
            'backup_auto_enabled' => 'boolean',
            'backup_frequency' => BackupFrequency::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * /theme.css is cached forever, not on a TTL — it must never serve a
     * color an admin already changed, so the cache is cleared the instant
     * this row saves instead. The old logo file is deleted the same way,
     * whenever it's replaced or cleared, since this singleton row is
     * never itself deleted.
     *
     * Every `static::saved()` listener here is wrapped to explicitly
     * return `void`, never the wrapped call's own return value — Laravel's
     * event dispatcher halts propagation to any *later* listener the
     * moment one returns exactly `false` (`Dispatcher::dispatch()`).
     * `Cache::forget()` returns `false` whenever the given key wasn't
     * already cached — a completely normal, expected case here, not a
     * failure — so leaving that return value unguarded would silently
     * stop the branded email theme below from ever regenerating whenever
     * `/theme.css` simply hadn't been requested yet.
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget(ThemeCssController::CACHE_KEY);
        });

        static::saved(function (): void {
            GenerateMailThemeCss::run();
        });

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
     *
     * `firstOrCreate` is a SELECT then an INSERT — not atomic — so two
     * concurrent first-touch requests against an empty table (e.g. two
     * admin tabs loading Store Settings right after a fresh deploy) could
     * both see no row and both try to insert one. `singleton_key`'s
     * unique constraint is what actually prevents a second row from ever
     * existing; the loser of that race catches the resulting constraint
     * violation here and re-fetches the winner's row instead.
     */
    public static function current(): self
    {
        try {
            $settings = self::query()->firstOrCreate(['singleton_key' => 'singleton']);
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'singleton_key')) {
                throw $e;
            }

            return self::query()->where('singleton_key', 'singleton')->firstOrFail();
        }

        return $settings->wasRecentlyCreated ? ($settings->fresh() ?? $settings) : $settings;
    }

    /**
     * The UTC instant that a calendar date starts at in the store's
     * configured display timezone — e.g. "2026-08-21" in a UTC+5 store
     * starts at "2026-08-20 19:00:00" UTC. Dashboard date-range filters
     * are chosen by an admin thinking in their own local calendar day,
     * but `created_at` is always stored in UTC — comparing a raw date
     * string against it directly (`whereDate('created_at', '>=', $date)`)
     * silently uses UTC day boundaries instead, which is invisible for a
     * UTC-timezone store but genuinely miscounts "today"/date-range data
     * by up to a day for any other configured timezone.
     */
    public function startOfDayUtc(string $date): Carbon
    {
        return Carbon::parse($date, $this->timezone)->startOfDay()->utc();
    }

    /**
     * The UTC instant a calendar date ends at in the store's timezone —
     * see startOfDayUtc() for why this matters.
     */
    public function endOfDayUtc(string $date): Carbon
    {
        return Carbon::parse($date, $this->timezone)->endOfDay()->utc();
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

    /**
     * The floating storefront chat bubble is only ever shown when an admin
     * has both switched it on AND actually provided a wa.me link to send
     * customers to — flipping the toggle on with no link set would render
     * a button with nowhere to go.
     */
    public function showsWhatsappChatBubble(): bool
    {
        return $this->whatsapp_chat_enabled && filled($this->whatsapp_url);
    }

    /**
     * Whether enough structured address data is on file to render a
     * meaningful `PostalAddress`/`geo` block in the `LocalBusiness`
     * JSON-LD schema — the free-text `contact_address` field alone isn't
     * structured enough for that, so this checks the dedicated fields.
     */
    public function hasStructuredAddress(): bool
    {
        return filled($this->address_street) && filled($this->address_city);
    }

    /**
     * Whether Google Analytics should load on the storefront — only when
     * an admin has actually set a measurement ID.
     */
    public function hasGoogleAnalytics(): bool
    {
        return filled($this->ga_measurement_id);
    }
}
