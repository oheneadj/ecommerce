<?php

/**
 * Extends Filament Breezy's own "Personal Info" profile tab to add the one
 * field this app's User model has that Breezy's default form doesn't know
 * about: phone. `$only` (Breezy's own allow-list for what gets read/saved)
 * must be extended too, or the field would render but silently never save.
 */

declare(strict_types=1);

namespace App\Filament\Breezy;

use App\Rules\PhoneNumber;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo as BasePersonalInfo;

class PersonalInfo extends BasePersonalInfo
{
    /** @var array<int, string> */
    public array $only = ['name', 'email', 'phone'];

    /** @return array<int, Component> */
    protected function getProfileFormComponents(): array
    {
        return [
            $this->getNameComponent(),
            $this->getEmailComponent(),
            $this->getPhoneComponent(),
            $this->getCurrentPasswordComponent(),
        ];
    }

    protected function getPhoneComponent(): TextInput
    {
        return TextInput::make('phone')
            ->tel()
            ->maxLength(255)
            // Normalizes on blur too, but that's client-side and skippable
            // (Enter key, autofill) — dehydrateStateUsing() is the real
            // guarantee, running server-side on every save. See the
            // identical comment on StaffForm's phone field.
            ->live(onBlur: true)
            ->afterStateUpdated(fn (?string $state, callable $set) => $set('phone', PhoneNumber::normalize((string) $state) ?? $state))
            ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : (PhoneNumber::normalize($state) ?? $state))
            ->unique($this->userClass, ignorable: $this->user)
            ->rule(new PhoneNumber)
            ->placeholder('e.g. +233201234567 or 0201234567')
            ->label('Phone');
    }
}
