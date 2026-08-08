<?php

/**
 * Daily safety-net sweep for variants at or below their low-stock threshold.
 */

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\UserRole;
use App\Enums\VariantStatus;
use App\Models\ProductVariant;
use App\Notifications\LowStockAlert;
use App\Notifications\Support\SafeNotifier;
use App\Notifications\Support\StaffRecipients;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * RecordStockMovement already alerts in real time the moment a variant
 * crosses its threshold — this scheduled sweep (daily) is the safety net
 * for anything that check could miss (a missed webhook, a threshold
 * lowered after the fact, etc.), per technical-design-ecommerce.md's
 * "daily or on stock_movement event" — this system does both.
 */
class CheckLowStockLevels
{
    use AsAction;

    public function handle(): int
    {
        $storeKeepers = StaffRecipients::forRole(UserRole::StoreKeeper->value);

        if ($storeKeepers->isEmpty()) {
            return 0;
        }

        $lowStockVariants = ProductVariant::query()
            ->where('status', VariantStatus::Active)
            ->lowStock()
            ->get();

        foreach ($lowStockVariants as $variant) {
            SafeNotifier::send($storeKeepers, new LowStockAlert($variant));
        }

        return $lowStockVariants->count();
    }
}
