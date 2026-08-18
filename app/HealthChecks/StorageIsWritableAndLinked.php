<?php

/**
 * Tier 1 (config/schema) — asserts the public storage symlink exists and uploads are writable.
 */

declare(strict_types=1);

namespace App\HealthChecks;

use Illuminate\Support\Facades\Storage;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Without `php artisan storage:link`, every product image/logo/attachment
 * upload succeeds server-side but 404s for every visitor — a failure mode
 * with no error anywhere in the request that caused it.
 */
class StorageIsWritableAndLinked extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $publicPath = public_path('storage');

        // Must be a real symlink, not merely something occupying that
        // path — a stray plain directory there (e.g. left over from a
        // broken deploy, created before `storage:link` was ever run)
        // passed this check before, while every upload still 404ed for
        // visitors: the write-probe below writes straight to
        // storage/app/public regardless, so it stayed green the entire
        // time public/storage wasn't actually routing there.
        if (! is_link($publicPath)) {
            return $result->failed('public/storage is not a symlink (uploaded files will 404 for every visitor even if this path exists as a plain directory). Fix: php artisan storage:link');
        }

        $target = readlink($publicPath);
        $expectedTarget = storage_path('app/public');

        if ($target === false || realpath($target) !== realpath($expectedTarget)) {
            return $result->failed("public/storage is a symlink but points somewhere unexpected ({$target}) instead of storage/app/public — uploaded files will 404. Fix: php artisan storage:link --force");
        }

        $probeFile = 'health-check-write-probe.txt';

        try {
            Storage::disk('public')->put($probeFile, 'ok');
            $written = Storage::disk('public')->exists($probeFile);
            Storage::disk('public')->delete($probeFile);
        } catch (\Throwable) {
            $written = false;
        }

        if (! $written) {
            return $result->failed('The public disk is not writable. Fix: check filesystem permissions on storage/app/public.');
        }

        return $result->ok('The public storage symlink exists, points to the right place, and the disk is writable.');
    }
}
