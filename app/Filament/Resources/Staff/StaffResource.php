<?php

/**
 * Invite/manage Admin and Store Keeper accounts. Super Admin stays CLI-only.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Staff;

use App\Enums\UserRole;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\Schemas\StaffForm;
use App\Filament\Resources\Staff\Tables\StaffTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Backed by `User`, like `CustomerResource` — but Filament resolves
 * `viewAny`/`create`/`update` authorization through a single Policy per
 * model class (`Gate::authorize($action, User::class)`), not per Resource
 * (see `vendor/filament/filament/src/Resources/Resource/Concerns/
 * HasAuthorization.php`). `UserPolicy` already governs `CustomerResource`
 * with Admin+Super-Admin access; reusing it here would wrongly grant Admin
 * staff-management access (BRD §3: only Super Admin manages staff/roles).
 * `canViewAny()`/`canCreate()`/`canEdit()` are overridden directly instead
 * — the same "intentional override" CLAUDE.md §18 itself allows. No
 * `canDelete` override: this resource has no delete action at all
 * (disable/enable instead — see `Tables\StaffTable`).
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'Staff member';

    /**
     * Scoped to Admin/Store Keeper only — Super Admin must 404 through
     * every route this resource has, not just be hidden from the list,
     * since a direct URL to a Super Admin's record ID resolves through
     * this same query. Not `->staff()` — Filament's base
     * `getEloquentQuery()` return type carries no model generic, so
     * PHPStan can't resolve a custom scope through Eloquent's magic
     * `__call()` here (same limitation `CustomerResource` documents for
     * its own inline filter). `User::scopeStaff()` is still the scope
     * used everywhere the query starts from `User::query()` directly.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas(
            'roles',
            fn (Builder $query) => $query->whereIn('name', [UserRole::Admin->value, UserRole::StoreKeeper->value]),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return self::isSuperAdmin();
    }

    private static function isSuperAdmin(): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }
}
