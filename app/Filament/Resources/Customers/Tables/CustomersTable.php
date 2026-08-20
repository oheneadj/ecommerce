<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use App\Actions\Customer\SendEmailToCustomer;
use App\Actions\Customer\SendSmsToCustomer;
use App\Actions\Customer\SetCustomerDisabledState;
use App\Filament\Resources\Customers\CustomerRecordActions;
use App\Models\User;
use App\Support\SanitizesExportFormulas;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Builds the customers list table — columns, row actions (email/SMS/
 * disable/enable), and bulk equivalents plus export.
 */
class CustomersTable
{
    /**
     * Configures the customer list table.
     */
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
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => $record->disabled_at === null ? 'Active' : 'Disabled')
                    ->color(fn (string $state): string => $state === 'Disabled' ? 'danger' : 'success'),
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
                    CustomerRecordActions::disable(),
                    CustomerRecordActions::enable(),
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
                    self::bulkDisableAction(),
                    self::bulkEnableAction(),
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withColumns([
                                    // Plain string column names here previously fataled at
                                    // export time ("Call to a member function getName() on
                                    // string") — withColumns() only accepts Column instances.
                                    // `name` is also customer-controlled free text, so it's
                                    // sanitized against CSV/Excel formula injection (a
                                    // customer setting their name to `=HYPERLINK(...)` could
                                    // otherwise execute when an admin opens the export).
                                    Column::make('name')->formatStateUsing(fn (?string $state) => SanitizesExportFormulas::sanitize($state)),
                                    Column::make('phone'),
                                    Column::make('email'),
                                    Column::make('orders_count'),
                                    Column::make('created_at'),
                                ]),
                        ]),
                ]),
            ])
            ->emptyStateHeading('No customers yet')
            ->emptyStateDescription('Customer accounts appear here once someone signs up.')
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }

    /**
     * Sends a bulk email to selected customers, skipping any without an
     * email on file.
     */
    private static function bulkSendEmailAction(): BulkAction
    {
        return BulkAction::make('bulkSendEmail')
            ->label('Send email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Send email')
            ->authorize(fn (): bool => Auth::user()?->can('viewAny', User::class) ?? false)
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

    /**
     * Sends a bulk SMS to selected customers, skipping any without a
     * phone number on file.
     */
    private static function bulkSendSmsAction(): BulkAction
    {
        return BulkAction::make('bulkSendSms')
            ->label('Send SMS')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Send SMS')
            ->authorize(fn (): bool => Auth::user()?->can('viewAny', User::class) ?? false)
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

    /**
     * Disables the selected customer accounts, signing them out and
     * blocking further login.
     */
    private static function bulkDisableAction(): BulkAction
    {
        return BulkAction::make('bulkDisable')
            ->label('Disable selected')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize(fn (): bool => Auth::user()?->can('viewAny', User::class) ?? false)
            ->requiresConfirmation()
            ->modalDescription('This immediately signs the selected customers out everywhere and blocks any further login attempts until re-enabled.')
            ->action(function (Collection $records): void {
                foreach ($records as $record) {
                    if ($record instanceof User) {
                        SetCustomerDisabledState::run($record, true);
                    }
                }

                Notification::make()->title("{$records->count()} customer(s) disabled")->success()->send();
            });
    }

    /**
     * Re-enables the selected customer accounts, allowing login again.
     */
    private static function bulkEnableAction(): BulkAction
    {
        return BulkAction::make('bulkEnable')
            ->label('Enable selected')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize(fn (): bool => Auth::user()?->can('viewAny', User::class) ?? false)
            ->requiresConfirmation()
            ->modalDescription('The selected customers will be able to log in again immediately.')
            ->action(function (Collection $records): void {
                foreach ($records as $record) {
                    if ($record instanceof User) {
                        SetCustomerDisabledState::run($record, false);
                    }
                }

                Notification::make()->title("{$records->count()} customer(s) enabled")->success()->send();
            });
    }

    /**
     * Shared success notification for the bulk email/SMS actions.
     */
    private static function notifyBulkResult(string $channel, int $sent, int $skipped, string $skipReason): void
    {
        Notification::make()
            ->title("{$channel} sent to {$sent} customer(s)".($skipped > 0 ? ", {$skipped} skipped ({$skipReason})" : ''))
            ->success()
            ->send();
    }
}
