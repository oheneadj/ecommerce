<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle state of a stock reservation held against a variant.
 */
enum StockReservationStatus: string
{
    case Active = 'active';
    case Fulfilled = 'fulfilled';
    case Released = 'released';
    case AtRisk = 'at_risk';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Fulfilled => 'Fulfilled',
            self::Released => 'Released',
            self::AtRisk => 'At Risk',
        };
    }
}
