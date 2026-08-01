<?php

/**
 * Shared Cedis-input helper for money form fields stored as integer pesewas.
 */

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

/**
 * Lets an admin type/see a Cedis amount (e.g. "30.00") while the underlying
 * model attribute stays an integer minor-unit value (pesewas), per the
 * project's money-handling convention — the conversion happens only at this
 * form boundary, never in the database or business logic.
 */
class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->step(0.01)
            ->prefix('GH₵')
            ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                $component->state($state === null ? null : round(((float) $state) / 100, 2));
            })
            ->dehydrateStateUsing(fn (mixed $state): ?int => ($state === null || $state === '') ? null : (int) round(((float) $state) * 100));
    }
}
