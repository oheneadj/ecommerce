<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Builds the category create/edit form: name, slug, and parent selection guarded against hierarchy cycles. */
class CategoryForm
{
    /** Configures the category form schema. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. T-Shirts')
                                    ->helperText('Category name displayed in navigation and filters. Slug will be generated automatically.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug())),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('auto-generated-from-name')
                                    ->unique(ignoreRecord: true),

                                Select::make('parent_id')
                                    ->label('Parent category')
                                    // Excludes the record itself and every
                                    // one of its own descendants — either
                                    // would create a cycle (A -> B -> A),
                                    // which nothing else here (schema, DB
                                    // constraint) prevents. No exclusion
                                    // needed on create; there's no record
                                    // yet to form a cycle with.
                                    ->options(function (?Category $record) {
                                        $query = Category::query();

                                        if ($record !== null) {
                                            $query->whereKeyNot($record->id)
                                                ->whereNotIn('id', $record->descendantIds());
                                        }

                                        return $query->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    // The excluded options above only hide
                                    // the choice client-side — a submitted
                                    // value is never restricted to what was
                                    // rendered, so the same self/descendant
                                    // check has to be re-enforced here too.
                                    ->rule(fn (?Category $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                        if ($record === null || $value === null) {
                                            return;
                                        }

                                        if ((int) $value === $record->id || in_array((int) $value, $record->descendantIds(), true)) {
                                            $fail('A category cannot be its own parent or descendant.');
                                        }
                                    }),
                            ]),
                    ]),
            ]);
    }
}
