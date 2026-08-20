<?php

/**
 * Shows an order's payments in a read-only table, with a Super-Admin-only
 * view action for provider callback detail.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\UserRole;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only — payments are never created, edited, or deleted from an
 * order's page (only InitiatePayment/webhooks touch a payment's status,
 * and refunds are issued from the standalone Payments resource). The
 * "view" modal reuses PaymentInfolist and stays Super-Admin-only, matching
 * the same gate PaymentsTable and ViewPayment already apply since it
 * surfaces the provider's raw callback metadata.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    /**
     * Builds the read-only payments table.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('provider_reference')
            ->columns([
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
            ->headerActions([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->button()
                    ->schema(fn (Schema $schema): Schema => PaymentInfolist::configure($schema))
                    // Unlike the standalone Payments resource's own
                    // ViewAction (which links to a dedicated page gated by
                    // ViewPayment::canAccess()), this relation manager
                    // renders the infolist inline via an explicit
                    // ->schema() — there's no page-route backstop here, so
                    // visible() alone (UI-hide only, not server-enforced
                    // against a direct mounted-action call) wasn't
                    // actually sufficient on its own. authorize() is —
                    // Filament checks it before the action is allowed to
                    // run, not just before rendering the button.
                    ->visible(fn (): bool => Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false)
                    ->authorize(fn (): bool => Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false),
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Payments will appear here once this order is checked out.')
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }
}
