<?php

/**
 * A single value of a global Attribute (e.g. "Large" under Size, or
 * "Red" under Color with a hex swatch).
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttributeTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $attribute_id
 * @property string $value
 * @property string $slug
 * @property string|null $swatch_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['attribute_id', 'value', 'slug', 'swatch_value'])]
class AttributeTerm extends Model
{
    /** @use HasFactory<AttributeTermFactory> */
    use HasFactory;

    /**
     * The attribute this term belongs to (e.g. Size).
     *
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Variants that have selected this term (e.g. every "Large" variant).
     *
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function productVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_attribute_term');
    }
}
