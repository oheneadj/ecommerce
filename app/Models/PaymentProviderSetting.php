<?php

/**
 * Whether a payment provider is enabled for checkout, and its display order.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use App\Enums\PaymentProvider;
use Database\Factories\PaymentProviderSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row per `PaymentProvider` enum case, auto-seeded via
 * `syncKnownProviders()` — never admin-created (no Create/Edit page; a
 * Filament list with an inline toggle + drag-reorder is the entire admin
 * surface). Multiple rows can be `enabled` at once; the customer picks
 * one of the enabled providers at checkout, in `sort_order`.
 *
 * @property int $id
 * @property PaymentProvider $provider
 * @property bool $enabled
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['provider', 'enabled', 'sort_order'])]
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
            'enabled' => 'boolean',
        ];
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
