<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes\Pages;

use App\Filament\Resources\Attributes\AttributeResource;
use App\Models\Attribute;
use App\Support\AttributeUsageSummary;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;

/** Edits a single attribute, blocking delete while it's still in use on any product/variant. */
class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    /** Header actions shown on the attribute edit form. */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->before(function (Attribute $record): void {
                    // attribute_terms/attribute_product/product_variant_attribute_term
                    // all cascadeOnDelete() — without this check, deleting an
                    // attribute silently strips it off every product/variant
                    // using it, and two variants that only differed by this
                    // attribute (e.g. Red/Large vs Blue/Large) become
                    // indistinguishable with no way to tell why. Blocking
                    // here mirrors CategoryResource's same-shaped guard.
                    $message = AttributeUsageSummary::forBlockedDelete(new Collection([$record]));

                    if ($message === null) {
                        return;
                    }

                    Notification::make()
                        ->title('Cannot delete attribute')
                        ->body($message)
                        ->danger()
                        ->send();

                    throw new Halt;
                }),
        ];
    }
}
