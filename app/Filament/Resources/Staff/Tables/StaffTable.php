<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Tables;

use App\Actions\Staff\SendStaffInviteNotification;
use App\Actions\Staff\SetStaffDisabledState;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Builds the admin table for browsing and managing staff accounts.
 */
class StaffTable
{
    /**
     * Configures columns, actions, and bulk actions for the staff table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->getStateUsing(fn (User $record): ?string => UserRole::tryFrom($record->getRoleNames()->first() ?? '')?->label()),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => self::statusFor($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Invited' => 'warning',
                        'Disabled' => 'danger',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label('Invited')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                self::resendInviteAction(),
                self::disableAction(),
                self::enableAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkDisableAction(),
                    self::bulkEnableAction(),
                ]),
            ])
            ->emptyStateHeading('No staff yet')
            ->emptyStateDescription('Invite an Admin or Store Keeper to get started.')
            ->emptyStateIcon(Heroicon::OutlinedUserPlus);
    }

    /**
     * Derives the display status ("Invited", "Disabled", "Active") for a staff record.
     */
    private static function statusFor(User $record): string
    {
        if ($record->disabled_at !== null) {
            return 'Disabled';
        }

        return $record->email_verified_at === null ? 'Invited' : 'Active';
    }

    /**
     * Builds the row action that re-sends the set-password invite to a still-invited staff member.
     */
    private static function resendInviteAction(): Action
    {
        return Action::make('resendInvite')
            ->label('Resend invite')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (User $record): bool => self::statusFor($record) === 'Invited')
            ->action(function (User $record): void {
                SendStaffInviteNotification::run($record);

                Notification::make()->title('Invite resent')->success()->send();
            });
    }

    /**
     * Builds the row action that disables a single staff member's account.
     */
    private static function disableAction(): Action
    {
        return Action::make('disable')
            ->label('Disable')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (User $record): bool => $record->disabled_at === null)
            ->authorize(fn (): bool => self::isSuperAdmin())
            ->requiresConfirmation()
            ->modalDescription('This immediately signs the account out everywhere. They will not be able to log in until re-enabled.')
            ->action(function (User $record): void {
                SetStaffDisabledState::run($record, true);

                Notification::make()->title('Staff member disabled')->success()->send();
            });
    }

    /**
     * Builds the row action that re-enables a single disabled staff member's account.
     */
    private static function enableAction(): Action
    {
        return Action::make('enable')
            ->label('Enable')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (User $record): bool => $record->disabled_at !== null)
            ->authorize(fn (): bool => self::isSuperAdmin())
            ->requiresConfirmation()
            ->modalDescription('This sends a new set-password invite — their previous password will no longer work.')
            ->action(function (User $record): void {
                SetStaffDisabledState::run($record, false);

                Notification::make()->title('Staff member enabled, a new invite was sent')->success()->send();
            });
    }

    /**
     * Builds the bulk action that disables all selected staff accounts.
     */
    private static function bulkDisableAction(): BulkAction
    {
        return BulkAction::make('bulkDisable')
            ->label('Disable selected')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize(fn (): bool => self::isSuperAdmin())
            ->requiresConfirmation()
            ->modalDescription('This immediately signs the selected accounts out everywhere. They will not be able to log in until re-enabled.')
            ->action(function (Collection $records): void {
                foreach ($records as $record) {
                    if ($record instanceof User) {
                        SetStaffDisabledState::run($record, true);
                    }
                }

                Notification::make()->title("{$records->count()} staff member(s) disabled")->success()->send();
            });
    }

    /**
     * Builds the bulk action that re-enables all selected staff accounts.
     */
    private static function bulkEnableAction(): BulkAction
    {
        return BulkAction::make('bulkEnable')
            ->label('Enable selected')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize(fn (): bool => self::isSuperAdmin())
            ->requiresConfirmation()
            ->modalDescription('This sends a new set-password invite to each selected account — their previous passwords will no longer work.')
            ->action(function (Collection $records): void {
                foreach ($records as $record) {
                    if ($record instanceof User) {
                        SetStaffDisabledState::run($record, false);
                    }
                }

                Notification::make()->title("{$records->count()} staff member(s) enabled")->success()->send();
            });
    }

    /**
     * Matches StaffResource::canViewAny() — the page itself already gates
     * every non-Super-Admin panel user out before this table ever
     * mounts, but these actions authorize independently too rather than
     * relying solely on that page-level gate.
     */
    private static function isSuperAdmin(): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }
}
