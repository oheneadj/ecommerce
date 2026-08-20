<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

/** Edits a single category, blocking delete while products are still assigned to it. */
class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    /** Header actions shown on the category edit form. */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Category $record): void {
                    // products.category_id is restrictOnDelete() at the DB
                    // level — without this check, deleting a category still
                    // in use throws an unhandled QueryException (a raw 500)
                    // instead of a clean, actionable message.
                    if ($record->products()->exists()) {
                        Notification::make()
                            ->title('Cannot delete category')
                            ->body('This category still has products assigned to it. Move or delete them first.')
                            ->danger()
                            ->send();

                        throw new Halt;
                    }
                }),
        ];
    }
}
