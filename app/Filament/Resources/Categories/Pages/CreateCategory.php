<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

/** Creates a new category. */
class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
