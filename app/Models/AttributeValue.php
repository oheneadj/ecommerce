<?php

/**
 * A single named attribute value (e.g. "Size: Large") on a product variant.
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_variant_id
 * @property string $attribute_name
 * @property string $value
 */
#[Fillable(['product_variant_id', 'attribute_name', 'value'])]
class AttributeValue extends Model
{
    public $timestamps = false;

    /**
     * The variant this attribute value belongs to.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
