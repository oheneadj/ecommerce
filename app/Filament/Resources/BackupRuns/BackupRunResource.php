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

    /**
     * Configures the backup runs list table.
     */
    public static function table(Table $table): Table
    {
        return BackupRunsTable::configure($table);
    }

    /**
     * Registers the resource's index page (read-only, no create/edit pages).
     */
    public static function getPages(): array
    {
        return [
            'index' => ListBackupRuns::route('/'),
        ];
    }

    /**
     * Backup runs are only ever created by the backup job, never through this panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Backup runs are read-only history, never editable through this panel.
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Restricts this resource to Super Admins only.
     */
    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }

    /**
     * Hides the navigation item from anyone who can't access the resource.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
