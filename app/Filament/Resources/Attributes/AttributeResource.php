<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes;

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Attributes\RelationManagers\TermsRelationManager;
use App\Filament\Resources\Attributes\Schemas\AttributeForm;
use App\Filament\Resources\Attributes\Tables\AttributesTable;
use App\Models\Attribute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Manages the global attribute catalog (Size, Color, Material...) — grouped
 * under the same "Catalog" nav section as Products, but not scoped to any
 * single product, since attributes are reused across the whole catalog.
 */
class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    /** Configures the attribute create/edit form. */
    public static function form(Schema $schema): Schema
    {
        return AttributeForm::configure($schema);
    }

    /** Configures the attributes list table. */
    public static function table(Table $table): Table
    {
        return AttributesTable::configure($table);
    }

    /** Registers the attribute values relation manager. */
    public static function getRelations(): array
    {
        return [
            TermsRelationManager::class,
        ];
    }

    /** Maps route names to their page classes. */
    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
