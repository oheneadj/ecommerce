<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes\Pages;

use App\Filament\Resources\Attributes\AttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Lists attributes for the Attribute resource. */
class ListAttributes extends ListRecords
{
    protected static string $resource = AttributeResource::class;

    /** Header actions shown on the attributes list. */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
