<?php

/**
 * Whether a payment provider is enabled for checkout, and its display order.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use App\Enums\PaymentProvider;
use App\Enums\PaystackCheckoutMode;
use Database\Factories\PaymentProviderSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One row per `PaymentProvider` enum case, auto-seeded via
 * `syncKnownProviders()` — never admin-created (no Create/Edit page; a
 * Filament list with an inline toggle + drag-reorder is the entire admin
 * surface, plus an edit modal for the presentational/mode fields below).
 * Multiple rows can be `enabled` at once; the customer picks one of the
 * enabled providers at checkout, in `sort_order`.
 *
 * @property int $id
 * @property PaymentProvider $provider
 * @property string|null $logo_path
 * @property-read string|null $logo_url
 * @property string|null $description
 * @property PaystackCheckoutMode|null $checkout_mode
 * @property bool $enabled
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['provider', 'logo_path', 'description', 'checkout_mode', 'enabled', 'sort_order'])]
class PaymentProviderSetting extends Model
{
    /** @use HasFactory<PaymentProviderSettingFactory> */
    use HasFactory, LogsAdminActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'checkout_mode' => PaystackCheckoutMode::class,
            'enabled' => 'boolean',
        ];
    }

    /**
     * The logo's public URL, or null when none has been uploaded — kept
     * here rather than computed inline in a view, so every consumer
     * (admin table, checkout page) reads the same resolved value instead
     * of each deriving its own Storage::disk()->url() call.
     *
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
        );
    }

    /**
     * Whether this row should use Paystack's popup checkout instead of a
     * full-page redirect — false for every other provider (and false for
     * Paystack itself until an admin explicitly picks Popup; Redirect
     * stays the default so an unconfigured deployment behaves exactly as
     * it always has).
     */
    public function usesPaystackPopup(): bool
    {
        return $this->provider === PaymentProvider::Paystack
            && $this->checkout_mode === PaystackCheckoutMode::Popup;
    }

    /**
     * Create a row for any `PaymentProvider` case that doesn't have one
     * yet, defaulted to disabled — called from the admin resource's
     * `getEloquentQuery()` so every known provider always appears in the
     * list, even ones never touched before.
     */
    public static function syncKnownProviders(): void
    {
        $nextSortOrder = ((int) static::query()->max('sort_order')) + 1;

        foreach (PaymentProvider::cases() as $case) {
            if (static::query()->where('provider', $case->value)->exists()) {
                continue;
            }

            static::query()->create([
                'provider' => $case->value,
                'enabled' => false,
                'sort_order' => $nextSortOrder++,
            ]);
        }
    }

    /**
     * @param  Builder<PaymentProviderSetting>  $query
     * @return Builder<PaymentProviderSetting>
     */
    public function scopeEnabledOrdered(Builder $query): Builder
    {
        return $query->where('enabled', true)->orderBy('sort_order');
    }
}
