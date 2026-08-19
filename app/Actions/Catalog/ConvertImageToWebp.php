<?php

/**
 * Converts a stored image file to WebP, in place, if it isn't already.
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Image\ImageException;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exceptions\ImageException as InterventionImageException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every product/variant/brand image upload goes through this — whatever
 * format a staff member uploads (JPEG, PNG, etc.), it's converted to WebP
 * on the way to disk, so the catalog never ends up with a mix of formats.
 * A file already saved as `.webp` is left untouched.
 */
class ConvertImageToWebp
{
    use AsAction;

    public function handle(string $disk, string $path): string
    {
        if (str($path)->lower()->endsWith('.webp')) {
            return $path;
        }

        if ($this->exceedsMaxPixels($disk, $path)) {
            // A small file that decodes to an enormous resolution (a
            // "decompression bomb") would otherwise exhaust memory on
            // this synchronous request the moment decode() below is
            // attempted — rejected here, before any decode happens at
            // all, rather than after the damage is already done.
            Storage::disk($disk)->delete($path);
            Log::warning('Rejected an image upload exceeding the max pixel count', ['disk' => $disk, 'path' => $path]);

            return $path;
        }

        $directory = pathinfo($path, PATHINFO_DIRNAME);

        try {
            $webpPath = Image::fromStorage($path, $disk)
                ->toWebp()
                ->store($directory === '.' ? '' : $directory, $disk);
        } catch (ImageException|InterventionImageException) {
            // Unsupported/undecodable file — keep the original rather than
            // losing the upload. Catches both Laravel's own wrapper
            // exception and Intervention Image's own exception hierarchy
            // directly — the underlying decode() call isn't wrapped by
            // Laravel's Image component, so a corrupt (or bomb-triggered
            // allocation failure) file previously reached here as an
            // uncaught Intervention exception instead of this fallback.
            return $path;
        }

        if ($webpPath === false) {
            return $path;
        }

        Storage::disk($disk)->delete($path);

        return $webpPath;
    }

    /**
     * A cheap, header-only read (no full decode, no bitmap allocation) —
     * safe to run even on a maliciously crafted file.
     */
    private function exceedsMaxPixels(string $disk, string $path): bool
    {
        $fullPath = Storage::disk($disk)->path($path);
        $size = @getimagesize($fullPath);

        if ($size === false) {
            // Not readable as an image at all (or a format getimagesize()
            // doesn't recognize) — not this check's concern, let the
            // normal decode attempt below handle it either way.
            return false;
        }

        [$width, $height] = $size;

        return $width * $height > (int) config('media.max_image_pixels');
    }

    /**
     * A drop-in replacement for a FileUpload field's default upload-saving
     * behavior: saves the file as usual, then converts the result to WebP.
     */
    public static function forFileUpload(): Closure
    {
        return function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
            $path = $component->saveUploadedFile($file);

            if ($path === null) {
                return null;
            }

            return self::run($component->getDiskName(), $path);
        };
    }
}
