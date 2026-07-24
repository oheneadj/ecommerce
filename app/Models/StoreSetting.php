<?php

/**
 * Singleton store-wide configuration.
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Always exactly one row. Business-behavior configuration (reservation
 * window, branding, etc.) lives here so it's admin-editable without a
 * deploy — never hardcode these values in an Action.
 *
 * @property int $id
 * @property int $stock_reservation_minutes
 */
#[Fillable(['stock_reservation_minutes'])]
class StoreSetting extends Model
{
    /**
     * Get the single settings row, creating it with defaults if missing.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
