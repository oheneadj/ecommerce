<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Models\Coupon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coupons index page, with tabs for active/inactive/expired coupons.
 */
class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    /**
     * Header actions for the list page.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Coupon status isn't one column — it's `active` plus `expires_at` — so
     * these tabs derive the same "is this coupon actually usable right now"
     * logic ApplyCouponToOrder enforces, rather than a plain enum value.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')
                ->query(fn (Builder $query): Builder => $query->where('active', true)
                    ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now())))
                ->badge(Coupon::query()->where('active', true)
                    ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->count()),
            'inactive' => Tab::make('Inactive')
                ->query(fn (Builder $query): Builder => $query->where('active', false))
                ->badge(Coupon::query()->where('active', false)->count()),
            'expired' => Tab::make('Expired')
                ->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()))
                ->badge(Coupon::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->count()),
        ];
    }
}
