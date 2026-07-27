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
use Illuminate\Support\Facades\Storage;
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

        $directory = pathinfo($path, PATHINFO_DIRNAME);

        try {
            $webpPath = Image::fromStorage($path, $disk)
                ->toWebp()
                ->store($directory === '.' ? '' : $directory, $disk);
        } catch (ImageException) {
            // Unsupported/undecodable file — keep the original rather than losing the upload.
            return $path;
        }

        if ($webpPath === false) {
            return $path;
        }

        Storage::disk($disk)->delete($path);

        return $webpPath;
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
