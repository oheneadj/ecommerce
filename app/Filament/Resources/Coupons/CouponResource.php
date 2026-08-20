<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Filament\Resources\Coupons\Schemas\CouponForm;
use App\Filament\Resources\Coupons\Tables\CouponsTable;
use App\Models\Coupon;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Filament resource for managing discount coupons — code, type, value,
 * usage limits, and product/category scope.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    /**
     * Configures the coupon create/edit form.
     */
    public static function form(Schema $schema): Schema
    {
        return CouponForm::configure($schema);
    }

    /**
     * Configures the coupons list table.
     */
    public static function table(Table $table): Table
    {
        return CouponsTable::configure($table);
    }

    /**
     * No relation managers on this resource.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Registers the pages available on this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
