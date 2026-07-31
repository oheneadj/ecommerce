<?php

/**
 * A reusable, global product attribute (e.g. Size, Color) — WooCommerce-style:
 * defined once with a type, then shared across any product that enables it,
 * instead of each variant retyping the same values.
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttributeType;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property AttributeType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'type'])]
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    /**
     * Use `slug` for route-model binding — never expose the raw bigint `id`.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
        ];
    }

    /**
     * The available values for this attribute (e.g. S, M, L for Size).
     *
     * @return HasMany<AttributeTerm, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(AttributeTerm::class);
    }

    /**
     * Products that have this attribute enabled.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
