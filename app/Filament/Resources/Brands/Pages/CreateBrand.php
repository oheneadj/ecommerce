<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use Filament\Resources\Pages\CreateRecord;

/** Creates a new brand. */
class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;
}
