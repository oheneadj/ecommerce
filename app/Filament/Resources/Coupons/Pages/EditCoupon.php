<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Models\Coupon;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

/**
 * Coupon edit page.
 */
class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    /**
     * Header actions for the edit page. Delete is blocked while the
     * coupon has any recorded usage.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Coupon $record): void {
                    // coupon_usages.coupon_id is restrictOnDelete() at the
                    // DB level — without this check, deleting a coupon
                    // that's already been used throws an unhandled
                    // QueryException (a raw 500) instead of a clean,
                    // actionable message. Same pattern as EditCategory.
                    if ($record->usages()->exists()) {
                        Notification::make()
                            ->title('Cannot delete coupon')
                            ->body('This coupon has already been used on one or more orders. Deactivate it instead of deleting it.')
                            ->danger()
                            ->send();

                        throw new Halt;
                    }
                }),
        ];
    }
}
