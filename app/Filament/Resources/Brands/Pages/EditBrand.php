<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use App\Models\Brand;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

/** Edits a single brand, cleaning up its stored logo file on delete. */
class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    /** Header actions shown on the brand edit form. */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Brand $record): void {
                    if ($record->logo_path) {
                        Storage::disk('public')->delete($record->logo_path);
                    }
                }),
        ];
    }
}
