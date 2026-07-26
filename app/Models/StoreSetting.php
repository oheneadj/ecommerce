<?php

/**
 * Singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Always exactly one row. Business-behavior configuration (reservation
 * window, branding, etc.) lives here so it's admin-editable without a
 * deploy — never hardcode these values in an Action.
 *
 * @property int $id
 * @property int $stock_reservation_minutes
 * @property int $low_stock_threshold
 */
#[Fillable(['stock_reservation_minutes', 'low_stock_threshold'])]
class StoreSetting extends Model
{
    use LogsAdminActivity;

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
