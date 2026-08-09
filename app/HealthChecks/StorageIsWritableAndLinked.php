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

        if (! is_link($publicPath) && ! is_dir($publicPath)) {
            return $result->failed('public/storage does not exist — uploaded files will 404 for every visitor. Fix: php artisan storage:link');
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

        return $result->ok('The public storage symlink exists and the disk is writable.');
    }
}
