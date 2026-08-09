<?php

/**
 * The product brand model.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use App\Observers\BrandObserver;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A brand a product can be attributed to. Uses `slug` as its external
 * identifier, consistent with Category and Product.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'logo_path', 'description'])]
#[ObservedBy(BrandObserver::class)]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, LogsAdminActivity;

    /**
     * Use `slug` for route-model binding — never expose the raw bigint `id`.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Products attributed to this brand.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
