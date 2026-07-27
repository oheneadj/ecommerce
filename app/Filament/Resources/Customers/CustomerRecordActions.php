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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

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
            ->visible(fn (User $record): bool => $record->email !== null)
            ->schema([
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255),
                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->rows(5),
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
            ->visible(fn (User $record): bool => $record->phone !== null)
            ->schema([
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
