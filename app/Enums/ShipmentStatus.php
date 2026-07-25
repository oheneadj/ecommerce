<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A shipment's fulfillment lifecycle state.
 */
enum ShipmentStatus: string
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
}
