<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Pages;

use App\Filament\Resources\StaticPages\StaticPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** Edits a single static page. */
class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    /** Header actions shown on the static page edit form. */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
