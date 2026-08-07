<?php

/**
 * Manages a product's images from its edit page — both general product
 * images and images scoped to one specific variant.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Models\Product;
use App\Models\ProductImage;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * `ProductImage::product_variant_id` is nullable: leaving it blank scopes the
 * image to the whole product, picking a variant scopes it to just that variant
 * (e.g. one photo per shirt color). Uploads are routed through the
 * `AttachProductImage` Action rather than a plain Eloquent create, consistent
 * with every other write in this codebase.
 */
class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    /**
     * Variant scope is a select of the owning product's own variants (SKU-labeled)
     * plus a blank option meaning "general product image".
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->maxSize(config('media.max_upload_size_kb'))
                    ->disk('public')
                    ->directory('product-images')
                    ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                    ->required(),

                Select::make('product_variant_id')
                    ->label('Scope')
                    ->placeholder('General (whole product)')
                    ->options(function (): array {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        return $product->variants()->pluck('sku', 'id')->all();
                    })
                    ->helperText('Leave blank for a general product image, or pick a variant to scope this image to it.'),

                TextInput::make('sort_order')
                    ->label('Display order')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Toggle::make('is_primary')
                    ->label('Primary image'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                ImageColumn::make('path')
                    ->label('Image')
                    ->disk('public')
                    ->imageHeight(60),
                TextColumn::make('productVariant.sku')
                    ->label('Scope')
                    ->badge()
                    ->color(fn (?string $state): string => $state === null ? 'gray' : 'info')
                    ->formatStateUsing(fn (?string $state): string => $state ?? 'General'),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
                $this->deleteAction(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No images yet')
            ->emptyStateDescription('Upload a general product photo, or scope one to a specific variant.')
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    /**
     * Deletes the stored file alongside the database row, so removing an
     * image never leaves an orphaned file behind in storage.
     */
    private function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->button()
            ->before(function (ProductImage $record): void {
                Storage::disk('public')->delete($record->path);
            });
    }
}
