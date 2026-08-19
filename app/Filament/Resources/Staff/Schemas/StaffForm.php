<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\PhoneNumber;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Staff details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Jane Doe'),

                                Select::make('role')
                                    ->options([
                                        UserRole::Admin->value => UserRole::Admin->label(),
                                        UserRole::StoreKeeper->value => UserRole::StoreKeeper->label(),
                                    ])
                                    ->required()
                                    ->helperText('Super Admin accounts are created via the CLI only, not from here.')
                                    // Not a real column — a Spatie role. Deliberately
                                    // still dehydrated (included in $data) since both
                                    // CreateStaff::handleRecordCreation() and
                                    // EditStaff::handleRecordUpdate() are fully
                                    // overridden and read it themselves (assignRole()/
                                    // syncRoles()) rather than relying on Filament's
                                    // default "mass-assign $data onto the model"
                                    // behavior — that's what dehydrated(false) would
                                    // otherwise be guarding against. On edit, pre-fill
                                    // from the record's current role, since there's no
                                    // real `role` attribute for Filament to hydrate
                                    // this from automatically.
                                    ->afterStateHydrated(function (Select $component, ?User $record): void {
                                        $component->state($record?->getRoleNames()->first());
                                    }),

                                TextInput::make('email')
                                    ->required()
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g. jane@example.com')
                                    ->helperText('The set-password invite is sent here.'),

                                TextInput::make('phone')
                                    ->required()
                                    ->tel()
                                    ->maxLength(255)
                                    // Normalizes on blur too, so the visible
                                    // field value matches what will actually be
                                    // saved before the admin submits — but the
                                    // blur event is client-side and skippable
                                    // (Enter key, autofill, a fast form fill), so
                                    // dehydrateStateUsing() is the real guarantee:
                                    // it runs server-side on every save
                                    // regardless of whether blur ever fired,
                                    // closing the gap where an unnormalized local-
                                    // format number could otherwise be persisted
                                    // and silently break SMS delivery/uniqueness
                                    // matching against the canonical E.164 form
                                    // every other phone input path stores.
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('phone', PhoneNumber::normalize((string) $state) ?? $state))
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : (PhoneNumber::normalize($state) ?? $state))
                                    ->unique(ignoreRecord: true)
                                    ->rule(new PhoneNumber)
                                    ->placeholder('e.g. +233201234567 or 0201234567')
                                    ->helperText('Used for the invite heads-up SMS and operational alerts (e.g. low stock).'),
                            ]),
                    ]),
            ]);
    }
}
