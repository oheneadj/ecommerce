<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Actions\Inventory\AdjustStockWithReservationCheck;
use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function handleRecordCreation(array $data): StockMovement
    {
        $variant = ProductVariant::query()->findOrFail($data['product_variant_id']);

        $result = AdjustStockWithReservationCheck::run(
            $variant,
            (int) $data['quantity'],
            Auth::user(),
            $data['note'] ?? null,
            StockMovementType::from($data['type']),
        );

        if ($result['at_risk_reservation_ids'] !== []) {
            Notification::make()
                ->title('Reservations flagged at risk')
                ->body('This adjustment left '.count($result['at_risk_reservation_ids']).' active reservation(s) uncovered by stock. They have been flagged for review.')
                ->warning()
                ->persistent()
                ->send();
        }

        return $result['movement'];
    }
}
