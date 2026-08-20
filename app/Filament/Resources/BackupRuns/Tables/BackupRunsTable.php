<?php

declare(strict_types=1);

namespace App\Filament\Resources\BackupRuns\Tables;

use App\Actions\Backup\RestoreFromBackup;
use App\Enums\BackupStatus;
use App\Exceptions\BackupNotRestorableException;
use App\Jobs\RunBackupJob;
use App\Models\BackupRun;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Builds the read-only admin table listing backup runs, plus the run-now
 * and restore actions.
 */
class BackupRunsTable
{
    /**
     * Configures columns and actions for the backup runs table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('triggeredBy.name')
                    ->label('Triggered by')
                    ->placeholder('Scheduled'),
                TextColumn::make('size')
                    ->label('Size')
                    ->state(fn (BackupRun $record): ?string => $record->sizeFormatted()),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->placeholder('—')
                    ->color('danger'),
            ])
            ->defaultSort('started_at', 'desc')
            ->recordActions([
                self::restoreAction(),
            ])
            ->headerActions([
                self::runNowAction(),
            ])
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('Run one now, or turn on automatic backups from Store Settings.')
            ->emptyStateIcon(Heroicon::OutlinedCircleStack);
    }

    /**
     * Builds the header action that dispatches an on-demand backup job.
     */
    private static function runNowAction(): Action
    {
        return Action::make('runNow')
            ->label('Run backup now')
            ->icon(Heroicon::OutlinedPlay)
            ->disabled(fn (): bool => BackupRun::query()->running()->exists())
            ->action(function (): void {
                $userId = Auth::id();

                RunBackupJob::dispatch($userId !== null ? (int) $userId : null);

                Notification::make()
                    ->title('Backup started')
                    ->body('This runs in the background — refresh in a few minutes to see the result.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Deliberately heavy to trigger: password re-entry AND a literal
     * typed confirmation phrase, on top of Super-Admin-only page access —
     * this overwrites the live database and every uploaded file.
     */
    private static function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (BackupRun $record): bool => $record->status === BackupStatus::Success)
            ->modalHeading('Restore this backup?')
            ->modalDescription('This overwrites the live database and every uploaded file with this backup\'s contents. This cannot be undone.')
            ->schema([
                TextInput::make('password')
                    ->label('Your password')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('confirmation')
                    ->label('Type RESTORE to confirm')
                    ->required(),
            ])
            ->action(function (array $data, BackupRun $record): void {
                if (! Hash::check($data['password'], Auth::user()->password)) {
                    Notification::make()->title('Incorrect password')->danger()->send();

                    return;
                }

                if ($data['confirmation'] !== 'RESTORE') {
                    Notification::make()->title('Confirmation phrase did not match')->danger()->send();

                    return;
                }

                try {
                    RestoreFromBackup::run($record);

                    Notification::make()->title('Restore complete')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Restore failed')
                        ->body($e instanceof BackupNotRestorableException ? $e->getMessage() : 'An unexpected error occurred.')
                        ->danger()
                        ->send();
                }
            });
    }
}
