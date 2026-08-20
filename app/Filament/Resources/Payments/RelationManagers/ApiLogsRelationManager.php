<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\RelationManagers;

use App\Models\PaymentApiLog;
use Filament\Actions\Action;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only — every outbound call to the payment provider for this payment,
 * with its full request/response payload. Written by the Action making the
 * call, never edited afterward.
 */
class ApiLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'apiLogs';

    protected static ?string $title = 'API Logs';

    /**
     * Configures the API logs table.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('provider')
                    ->badge(),
                TextColumn::make('status_code')
                    ->label('HTTP status')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state < 300 => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                //
            ])
            ->recordActions([
                self::viewPayloadAction(),
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No API calls logged yet')
            ->emptyStateIcon(Heroicon::OutlinedCodeBracket);
    }

    /**
     * Builds the row action that shows a log's request/response payloads.
     */
    private static function viewPayloadAction(): Action
    {
        return Action::make('viewPayload')
            ->label('View payload')
            ->button()
            ->modalHeading('Request / response payload')
            ->schema([
                TextEntry::make('action'),
                CodeEntry::make('request_payload')
                    ->label('Request payload')
                    ->grammar('json')
                    ->state(fn (PaymentApiLog $record): string => self::toJson($record->request_payload)),
                CodeEntry::make('response_payload')
                    ->label('Response payload')
                    ->grammar('json')
                    ->state(fn (PaymentApiLog $record): string => self::toJson($record->response_payload ?? [])),
            ]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function toJson(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT) ?: '{}';
    }
}
