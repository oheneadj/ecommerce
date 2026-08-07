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

];
