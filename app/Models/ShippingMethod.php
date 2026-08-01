<?php

/**
 * A selectable shipping option with a fixed cost.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasFormattedMoney;
use App\Concerns\HasUlid;
use App\Concerns\LogsAdminActivity;
use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property int $cost
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'cost', 'active'])]
class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory, HasFormattedMoney, HasUlid, LogsAdminActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Shipments created using this method.
     *
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * The shipping cost formatted for display (e.g. "GH₵15.50").
     */
    public function getCostFormattedAttribute(): string
    {
        return $this->formattedMoney($this->cost);
    }
}
