<?php

/**
 * Keeps the `brands` disk in sync with the `logo_path` column — deletes the
 * old logo file when it's replaced or removed, and the current one when the
 * brand itself is deleted, so neither a replace nor a delete ever leaves an
 * orphaned file behind in storage.
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\Brand;
use Illuminate\Support\Facades\Storage;

class BrandObserver
{
    public function saving(Brand $brand): void
    {
        if (! $brand->isDirty('logo_path')) {
            return;
        }

        $original = $brand->getOriginal('logo_path');

        if ($original) {
            Storage::disk('public')->delete($original);
        }
    }

    public function deleted(Brand $brand): void
    {
        if ($brand->logo_path) {
            Storage::disk('public')->delete($brand->logo_path);
        }
    }
}
