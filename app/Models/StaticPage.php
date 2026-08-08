<?php

/**
 * Admin-authored content page (About, Contact, Terms, etc.).
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LogsAdminActivity;
use Database\Factories\StaticPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Backend-only for now (Epic E12.8) — content can be authored ahead of the
 * storefront existing, but nothing publicly renders it yet. Uses `slug`
 * (not a `ulid`) as its external identifier, same as Category/Brand, since
 * a content page's slug is itself the intended public URL segment.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property bool $is_published
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'slug', 'content', 'is_published', 'meta_title', 'meta_description'])]
class StaticPage extends Model
{
    /** @use HasFactory<StaticPageFactory> */
    use HasFactory, LogsAdminActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * Use `slug` for route-model binding — it's the intended public URL segment.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
