<?php

declare(strict_types=1);

namespace App\Filament\Resources\BackupRuns;

use App\Enums\UserRole;
use App\Filament\Resources\BackupRuns\Pages\ListBackupRuns;
use App\Filament\Resources\BackupRuns\Tables\BackupRunsTable;
use App\Models\BackupRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Read-only history of database + uploaded-files backups (App\Jobs\
 * RunBackupJob) — rows are never created/edited through this panel, only
 * by the job/listeners. Super Admin only, same as Store Settings and
 * System Health, since backups (and restoring one) affect the whole
 * deployment.
 */
class BackupRunResource extends Resource
{
    protected static ?string $model = BackupRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Backups';

    public static function table(Table $table): Table
    {
        return BackupRunsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBackupRuns::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
