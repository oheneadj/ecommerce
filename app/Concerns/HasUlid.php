<?php

/**
 * Adds an opaque, externally-safe ULID identifier to a model, layered
 * underneath its internal bigint primary key.
 */

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids as EloquentHasUlids;

/**
 * Generates a `ulid` column on creation and uses it as the route-model-binding
 * key, so the model's internal bigint `id` is never exposed in a route, URL,
 * or API response. The bigint `id` remains the actual primary key for joins.
 */
trait HasUlid
{
    use EloquentHasUlids;

    /**
     * Generate the ULID into a dedicated `ulid` column rather than replacing the primary key.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * Use the `ulid` column (not the internal bigint `id`) for route-model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
