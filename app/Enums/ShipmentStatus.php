<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A shipment's fulfillment lifecycle state.
 */
enum ShipmentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Dispatched => 'Dispatched',
            self::Delivered => 'Delivered',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Dispatched => 'warning',
            self::Delivered => 'success',
        };
    }
}
