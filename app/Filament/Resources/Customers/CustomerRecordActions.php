<?php

/**
 * The record-scoped "Send email"/"Send SMS" actions shared between the
 * Customers table row and the customer's own view page.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Actions\Customer\SendEmailToCustomer;
use App\Actions\Customer\SendSmsToCustomer;
use App\Actions\Customer\SetCustomerDisabledState;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class CustomerRecordActions
{
    /**
     * Hidden for a customer with no email on file — never reaches the
     * Action's own guard in normal use.
     */
    public static function sendEmail(): Action
    {
        return Action::make('sendEmail')
            ->label('Send email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Send email')
            ->visible(fn (User $record): bool => $record->email !== null)
            ->authorize(fn (User $record): bool => Auth::user()?->can('sendCommunication', $record) ?? false)
            ->schema([
                TextInput::make('to')
                    ->label('To')
                    ->default(fn (User $record): ?string => $record->email)
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255),
                RichEditor::make('body')
                    ->label('Body')
                    ->required(),
            ])
            ->action(function (User $record, array $data): void {
                SendEmailToCustomer::run($record, $data['subject'], $data['body']);

                Notification::make()->title('Email sent')->success()->send();
            });
    }

    /**
     * Hidden for a customer with no phone on file.
     */
    public static function sendSms(): Action
    {
        return Action::make('sendSms')
            ->label('Send SMS')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Send SMS')
            ->visible(fn (User $record): bool => $record->phone !== null)
            ->authorize(fn (User $record): bool => Auth::user()?->can('sendCommunication', $record) ?? false)
            ->schema([
                TextInput::make('to')
                    ->label('To')
                    ->default(fn (User $record): ?string => $record->phone)
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('message')
                    ->required()
                    ->rows(4),
            ])
            ->action(function (User $record, array $data): void {
                SendSmsToCustomer::run($record, $data['message']);

                Notification::make()->title('SMS sent')->success()->send();
            });
    }

    public static function disable(): Action
    {
        return Action::make('disable')
            ->label('Disable')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (User $record): bool => $record->disabled_at === null)
            ->authorize(fn (User $record): bool => Auth::user()?->can('setDisabledState', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription('This immediately signs the customer out everywhere and blocks any further login attempts until re-enabled.')
            ->action(function (User $record): void {
                SetCustomerDisabledState::run($record, true);

                Notification::make()->title('Customer disabled')->success()->send();
            });
    }

    public static function enable(): Action
    {
        return Action::make('enable')
            ->label('Enable')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (User $record): bool => $record->disabled_at !== null)
            ->authorize(fn (User $record): bool => Auth::user()?->can('setDisabledState', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription('The customer will be able to log in again immediately.')
            ->action(function (User $record): void {
                SetCustomerDisabledState::run($record, false);

                Notification::make()->title('Customer enabled')->success()->send();
            });
    }
}
