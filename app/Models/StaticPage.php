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
use Illuminate\Support\Str;

/**
 * Admin-authored content page, publicly rendered at its `slug` route once
 * published. Uses `slug` (not a `ulid`) as its external identifier, same
 * as Category/Brand, since a content page's slug is itself the intended
 * public URL segment.
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

    /**
     * `content` is raw HTML from the admin's rich text editor — sanitized
     * here so every render site gets safe-by-default output instead of
     * each caller needing to remember to strip scripts/handlers itself.
     */
    public function getSanitizedContentAttribute(): string
    {
        return Str::sanitizeHtml((string) $this->content);
    }
}
