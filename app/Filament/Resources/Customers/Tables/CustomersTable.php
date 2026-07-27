<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use App\Actions\Customer\SendEmailToCustomer;
use App\Actions\Customer\SendSmsToCustomer;
use App\Filament\Resources\Customers\CustomerRecordActions;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—'),
                IconColumn::make('google_id')
                    ->label('Google')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->google_id !== null),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search by name, email, or phone…')
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    CustomerRecordActions::sendEmail(),
                    CustomerRecordActions::sendSms(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size(Size::Small)
                    ->color('primary')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkSendEmailAction(),
                    self::bulkSendSmsAction(),
                ]),
            ])
            ->emptyStateHeading('No customers yet')
            ->emptyStateDescription('Customer accounts appear here once someone signs up.')
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }

    private static function bulkSendEmailAction(): BulkAction
    {
        return BulkAction::make('bulkSendEmail')
            ->label('Send email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->schema([
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255),
                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->rows(5),
            ])
            ->action(function (Collection $records, array $data): void {
                $sent = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record instanceof User || $record->email === null) {
                        $skipped++;

                        continue;
                    }

                    SendEmailToCustomer::run($record, $data['subject'], $data['body']);
                    $sent++;
                }

                self::notifyBulkResult('Email', $sent, $skipped, 'no email on file');
            });
    }

    private static function bulkSendSmsAction(): BulkAction
    {
        return BulkAction::make('bulkSendSms')
            ->label('Send SMS')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->schema([
                Textarea::make('message')
                    ->required()
                    ->rows(4),
            ])
            ->action(function (Collection $records, array $data): void {
                $sent = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record instanceof User || $record->phone === null) {
                        $skipped++;

                        continue;
                    }

                    SendSmsToCustomer::run($record, $data['message']);
                    $sent++;
                }

                self::notifyBulkResult('SMS', $sent, $skipped, 'no phone on file');
            });
    }

    private static function notifyBulkResult(string $channel, int $sent, int $skipped, string $skipReason): void
    {
        Notification::make()
            ->title("{$channel} sent to {$sent} customer(s)".($skipped > 0 ? ", {$skipped} skipped ({$skipReason})" : ''))
            ->success()
            ->send();
    }
}
