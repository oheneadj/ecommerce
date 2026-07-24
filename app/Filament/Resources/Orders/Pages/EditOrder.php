<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LogicException;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Order) {
            throw new LogicException('Expected an Order model.');
        }

        return UpdateOrderStatus::run(
            $record,
            OrderStatus::from($data['status']),
            Auth::user(),
            $data['status_change_note'] ?? null,
        );
    }
}
