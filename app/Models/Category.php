<?php

/**
 * The product category model.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A product category, supporting nested subcategories via a self-referential
 * `parent_id`. Uses `slug` (not a `ulid`) as its external identifier, since
 * slugs are both opaque enough and SEO-valuable for public category routes.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['parent_id', 'name', 'slug'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, LogsAdminActivity;

    /**
     * Use `slug` for route-model binding — never expose the raw bigint `id`.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The parent category, if this is a subcategory.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Direct subcategories of this category.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Products directly assigned to this category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * IDs of every category nested under this one at any depth — used to
     * stop the admin form from letting a category become its own
     * ancestor (a cycle), which nothing else in the schema/DB prevents.
     * A plain recursive walk rather than a raw recursive CTE: category
     * trees in a single-store catalog are shallow, and this only ever
     * runs once per admin form render, not on any customer-facing path.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = [...$ids, ...$child->descendantIds()];
        }

        return $ids;
    }
}
