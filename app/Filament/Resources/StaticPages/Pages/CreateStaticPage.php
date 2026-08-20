<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Pages;

use App\Filament\Resources\StaticPages\StaticPageResource;
use Filament\Resources\Pages\CreateRecord;

/** Creates a new static page. */
class CreateStaticPage extends CreateRecord
{
    protected static string $resource = StaticPageResource::class;
}
