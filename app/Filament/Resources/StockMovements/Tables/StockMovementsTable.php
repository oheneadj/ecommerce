<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\StockMovementType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productVariant.sku')
                    ->label('Variant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('quantity')
                    ->sortable(),
                TextColumn::make('note')
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(StockMovementType::class),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withColumns([
                                    'productVariant.sku',
                                    'type',
                                    'quantity',
                                    'note',
                                    'user.name',
                                    'created_at',
                                ]),
                        ]),
                ]),
            ])
            ->emptyStateHeading('No stock movements yet')
            ->emptyStateDescription('Record a manual restock or adjustment to get started.')
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
