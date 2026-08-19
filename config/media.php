<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Max Upload Size
    |--------------------------------------------------------------------------
    |
    | Maximum size, in kilobytes, accepted by every admin image upload field
    | (brand logo, attribute swatch, product image, variant image).
    |
    */

    'max_upload_size_kb' => (int) env('MEDIA_MAX_UPLOAD_SIZE_KB', 5120),

    /*
    |--------------------------------------------------------------------------
    | Max Image Pixel Count
    |--------------------------------------------------------------------------
    |
    | A file well under max_upload_size_kb can still decode to an enormous
    | resolution (a "decompression bomb") — GD allocates a full bitmap
    | (width x height x 4 bytes) at decode time regardless of the
    | compressed file size, which can exhaust PHP's memory_limit on the
    | synchronous request App\Actions\Catalog\ConvertImageToWebp runs on.
    | Checked via a cheap header-only read (getimagesize()) before any
    | decode is attempted. Default caps at roughly a 6000x6666 image —
    | generous for any real product/logo photo.
    |
    */

    'max_image_pixels' => (int) env('MEDIA_MAX_IMAGE_PIXELS', 40_000_000),

    /*
    |--------------------------------------------------------------------------
    | Catalog Limits
    |--------------------------------------------------------------------------
    |
    | Server-side caps enforced by App\Filament\Resources\Products'
    | ImagesRelationManager and App\Actions\Catalog\GenerateProductVariants —
    | these were previously documented in .env.example but never actually
    | read anywhere, so a crafted request (or an admin selecting a large
    | attribute set) could create an unbounded number of images/variants.
    |
    */

    'product_max_images' => (int) env('PRODUCT_MAX_IMAGES', 5),

    'product_max_variants' => (int) env('PRODUCT_MAX_VARIANTS', 10),

];
