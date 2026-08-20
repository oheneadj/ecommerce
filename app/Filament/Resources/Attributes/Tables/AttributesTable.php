<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes\Tables;

use App\Models\Attribute;
use App\Models\ProductVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/** Builds the attributes list table: columns, actions, and bulk delete with impact warnings. */
class AttributesTable
{
    /** Configures the attributes list table. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('terms_count')
                    ->label('Values')
                    ->counts('terms'),
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
                        ->authorizeIndividualRecords('delete')
                        ->requiresConfirmation()
                        ->modalDescription(function (Collection $records): string {
                            /** @var Collection<int, Attribute> $records */
                            $productCount = $records->sum(fn (Attribute $attribute): int => $attribute->products()->count());
                            $variantCount = ProductVariant::query()
                                ->whereHas('attributeTerms', fn ($query) => $query->whereIn('attribute_id', $records->pluck('id')))
                                ->count();

                            if ($productCount === 0 && $variantCount === 0) {
                                return 'This will permanently delete the selected attributes and all their values.';
                            }

                            return "These attributes are used by {$productCount} product(s) and assigned on {$variantCount} variant(s) in total. Deleting them removes them from all of those immediately and permanently.";
                        }),
                ]),
            ])
            ->emptyStateHeading('No attributes yet')
            ->emptyStateDescription('Create an attribute (e.g. Size, Color) to start reusing it across products.')
            ->emptyStateIcon(Heroicon::OutlinedTag)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
