<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Actions\Payment\ProcessRefund;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\RefundExceedsPaymentException;
use App\Filament\Support\MoneyInput;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PaymentsTable
{
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
                TextColumn::make('channel')
                    ->placeholder('—'),
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
                    ->visible(fn (): bool => Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false),
                self::refundAction(),
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Payments will appear here once customers start checking out.')
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }

    private static function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Issue refund')
            ->button()
            ->visible(fn (Payment $record) => $record->status === PaymentStatus::Success)
            ->schema([
                MoneyInput::make('amount')
                    ->label('Amount')
                    ->required()
                    ->helperText(fn (Payment $record) => "Payment amount: {$record->amount_formatted}"),
                TextInput::make('reason')
                    ->maxLength(255),
            ])
            ->action(function (Payment $record, array $data): void {
                try {
                    ProcessRefund::run($record, (int) $data['amount'], $data['reason'] ?? null);

                    Notification::make()->title('Refund processed')->success()->send();
                } catch (RefundExceedsPaymentException $e) {
                    Notification::make()->title('Refund failed')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
