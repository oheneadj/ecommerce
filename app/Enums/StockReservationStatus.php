<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The lifecycle state of a stock reservation held against a variant.
 */
enum StockReservationStatus: string implements HasColor, HasLabel
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

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Fulfilled => 'success',
            self::Released => 'gray',
            self::AtRisk => 'danger',
        };
    }
}
