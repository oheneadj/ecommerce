<?php

/**
 * The record-scoped "Send email"/"Send SMS" actions shared between the
 * Customers table row and the customer's own view page.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Actions\Customer\SendEmailToCustomer;
use App\Actions\Customer\SendSmsToCustomer;
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
}
