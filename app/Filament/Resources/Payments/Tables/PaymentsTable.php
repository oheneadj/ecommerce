<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Actions\Payment\ProcessRefund;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidRefundAmountException;
use App\Exceptions\RefundExceedsPaymentException;
use App\Filament\Support\MoneyInput;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Builds the admin table for browsing payments and issuing refunds.
 */
class PaymentsTable
{
    /**
     * Configures the payments table's columns, filters, actions, and export.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paystack' => 'info',
                        'moolre' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('amount_formatted')
                    ->label('Amount'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(PaymentStatus::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->button()
                    // Also backstopped by ViewPayment::canAccess() on the
                    // page this links to, but authorize() closes the same
                    // "visible() alone doesn't block a direct mounted-
                    // action call" gap as PaymentsRelationManager's own
                    // ViewAction, for defense-in-depth/consistency.
                    ->visible(fn (): bool => Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false)
                    ->authorize(fn (): bool => Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false),
                self::refundAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                // Plain string column names previously fataled at export
                                // time — withColumns() only accepts Column instances.
                                ->withColumns([
                                    Column::make('order.order_number'),
                                    Column::make('provider'),
                                    Column::make('amount'),
                                    Column::make('status'),
                                    Column::make('created_at'),
                                ]),
                        ]),
                ]),
            ])
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Payments will appear here once customers start checking out.')
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }

    /**
     * Builds the "issue refund" row action, queuing the refund via
     * ProcessRefund and surfacing any validation failure as a notification.
     */
    private static function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Issue refund')
            ->button()
            ->visible(fn (Payment $record) => $record->status === PaymentStatus::Success)
            ->authorize(fn (Payment $record): bool => Auth::user()?->can('update', $record) ?? false)
            ->schema([
                MoneyInput::make('amount')
                    ->label('Amount')
                    ->required()
                    ->minValue(0.01)
                    ->helperText(fn (Payment $record) => "Payment amount: {$record->amount_formatted}"),
                TextInput::make('reason')
                    ->maxLength(255),
            ])
            ->action(function (Payment $record, array $data): void {
                try {
                    ProcessRefund::run($record, (int) $data['amount'], $data['reason'] ?? null);

                    // The gateway call itself is queued (IssueProviderRefund) —
                    // this only confirms the refund was accepted and reserved
                    // against the payment's balance, not that it's settled yet.
                    Notification::make()->title('Refund queued')->body('It will be processed shortly — check the Refunds tab for its final status.')->success()->send();
                } catch (RefundExceedsPaymentException|InvalidRefundAmountException $e) {
                    Notification::make()->title('Refund failed')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
