<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes\RelationManagers;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Enums\AttributeType;
use App\Models\Attribute;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Manages a single attribute's values (e.g. Size's S/M/L/XL) — the swatch
 * field shown depends on the parent attribute's type, so a Color attribute
 * gets a color picker per value and an Image attribute gets an upload,
 * while Text attributes get neither.
 */
class TermsRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';

    /**
     * The parent Attribute this relation manager's terms belong to,
     * typed — `getOwnerRecord()` itself only returns a plain `Model`.
     */
    private function ownerAttribute(): Attribute
    {
        /** @var Attribute $attribute */
        $attribute = $this->getOwnerRecord();

        return $attribute;
    }

    public function form(Schema $schema): Schema
    {
        $attribute = $this->ownerAttribute();

        // Only one of these is ever built for a given attribute — a Text
        // attribute needs neither, so binding both under the same
        // 'swatch_value' key regardless of type corrupts the state (the
        // dormant field's empty array/null value overwrites the active one).
        $swatchField = match ($attribute->type) {
            AttributeType::Color => ColorPicker::make('swatch_value')->label('Color')->required(),
            AttributeType::Image => FileUpload::make('swatch_value')
                ->label('Image')
                ->image()
                ->maxSize(config('media.max_upload_size_kb'))
                ->disk('public')
                ->directory('attribute-swatches')
                ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                ->required(),
            AttributeType::Text => null,
        };

        return $schema
            ->components(array_filter([
                TextInput::make('value')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Large, Red')
                    ->helperText('Values must be unique within this attribute — the same value is fine under a different attribute.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug()))
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                            fn (Builder $query) => $query->where('attribute_id', $attribute->id),
                        ),
                    ),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                            fn (Builder $query) => $query->where('attribute_id', $attribute->id),
                        ),
                    ),

                $swatchField,
            ]));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->columns([
                TextColumn::make('value')
                    ->searchable(),
                TextColumn::make('slug'),
                ColorColumn::make('swatch_value')
                    ->label('Swatch')
                    ->visible(fn (): bool => $this->ownerAttribute()->type === AttributeType::Color),
                ImageColumn::make('swatch_value')
                    ->label('Swatch')
                    ->visible(fn (): bool => $this->ownerAttribute()->type === AttributeType::Image),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No values yet')
            ->emptyStateDescription('Add the values this attribute can take (e.g. S, M, L, XL for Size).');
    }
}
