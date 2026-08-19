<?php

/**
 * Applies a manual stock correction, flagging any reservation it can no longer cover.
 */

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Enums\UserRole;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Notifications\ReservationsAtRiskAlert;
use App\Notifications\Support\SafeNotifier;
use App\Notifications\Support\StaffRecipients;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A physical stock count is always authoritative over the movement ledger
 * (BRD FR-2.2a / business rule #15). If the correction leaves less stock
 * than is already held by active reservations, the adjustment still
 * proceeds — it is never blocked or silently ignored. Instead, the oldest
 * active reservations that can no longer be covered are flagged `at_risk`
 * for an Admin to resolve manually (contact customer, cancel, or expedite
 * restock); a later payment against an `at_risk` reservation is handled by
 * HandleLatePaymentConfirmation, not by this Action.
 *
 * No locking of its own — RecordStockMovement locks the variant row
 * internally for the duration of its own transaction (see that class),
 * which is sufficient for the write this Action makes.
 */
class AdjustStockWithReservationCheck
{
    use AsAction;

    /**
     * @return array{movement: StockMovement, at_risk_reservation_ids: array<int, int>}
     */
    public function handle(
        ProductVariant $variant,
        int $quantityChange,
        User $user,
        ?string $note = null,
        StockMovementType $type = StockMovementType::Adjustment,
    ): array {
        return DB::transaction(function () use ($variant, $quantityChange, $user, $note, $type): array {
            $movement = RecordStockMovement::run(
                $variant,
                $type,
                $quantityChange,
                $user,
                $note,
            );

            $variant->refresh();

            $activeReservations = StockReservation::query()
                ->where('product_variant_id', $variant->id)
                ->where('status', StockReservationStatus::Active)
                ->oldest('id')
                ->get();

            $shortfall = $activeReservations->sum('quantity') - $variant->stock;

            $atRiskIds = [];

            if ($shortfall > 0) {
                foreach ($activeReservations as $reservation) {
                    if ($shortfall <= 0) {
                        break;
                    }

                    $reservation->update(['status' => StockReservationStatus::AtRisk]);
                    $atRiskIds[] = $reservation->id;
                    $shortfall -= $reservation->quantity;
                }
            }

            if ($atRiskIds !== []) {
                DB::afterCommit(fn () => SafeNotifier::send(
                    StaffRecipients::forRole(UserRole::Admin->value)->merge(StaffRecipients::forRole(UserRole::SuperAdmin->value)),
                    new ReservationsAtRiskAlert($variant, $atRiskIds),
                ));
            }

            return ['movement' => $movement, 'at_risk_reservation_ids' => $atRiskIds];
        });
    }
}
