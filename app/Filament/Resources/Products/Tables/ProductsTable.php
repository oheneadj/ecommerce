<?php

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\DeleteProductImageFiles;
use App\Actions\Catalog\DuplicateProduct;
use App\Enums\ProductStatus;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(ProductStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->requiresConfirmation()
                    ->modalDescription('Creates a full copy of this product (variants, attributes, and images) as a new Draft product.')
                    ->action(function (Product $record): void {
                        $copy = DuplicateProduct::run($record);

                        Notification::make()
                            ->title('Product duplicated')
                            ->body("\"{$copy->name}\" was created as a draft.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (Collection $records): void {
                            foreach ($records as $record) {
                                if ($record instanceof Product) {
                                    DeleteProduct::run($record);
                                }
                            }
                        }),
                    ForceDeleteBulkAction::make()
                        ->before(function (Collection $records): void {
                            foreach ($records as $record) {
                                if ($record instanceof Product) {
                                    DeleteProductImageFiles::run($record);
                                }
                            }
                        }),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withColumns(['name', 'category.name', 'brand.name', 'status', 'created_at']),
                        ]),
                ]),
            ])
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Create your first product to start building your catalog.')
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
