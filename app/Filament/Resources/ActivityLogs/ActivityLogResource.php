<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only audit trail of admin actions on "key records" (FR-10.2),
 * populated automatically by App\Concerns\LogsAdminActivity on every
 * model that uses it — never written to directly. Super Admin only
 * (BRD E11.4 — narrower than every other staff-facing resource in this
 * system, which are Admin+Super Admin).
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Activity Log';

    /**
     * Configures the activity log list table.
     */
    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    /**
     * Eager loads the causer relation for the list table.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer']);
    }

    /**
     * Registers the pages available on this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }

    /**
     * Activity log entries are never created via the admin panel.
     */
    public static function canCreate(): bool
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
     * Hides the nav item for anyone who can't access the resource.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
