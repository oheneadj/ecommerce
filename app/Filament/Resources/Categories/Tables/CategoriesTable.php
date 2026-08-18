<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records): void {
                            // Same restrictOnDelete() constraint as the
                            // single-record delete (EditCategory) — checked
                            // up front here too, so a bulk selection that
                            // includes even one in-use category doesn't
                            // crash with an unhandled QueryException.
                            /** @var Collection<int, Category> $records */
                            $inUse = $records->filter(fn (Category $category): bool => $category->products()->exists());

                            if ($inUse->isNotEmpty()) {
                                Notification::make()
                                    ->title('Cannot delete category')
                                    ->body("{$inUse->count()} of the selected categories still have products assigned. Move or delete them first.")
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No categories yet')
            ->emptyStateDescription('Create a category to start organizing your products.')
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
