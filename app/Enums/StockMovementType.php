<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The reason a stock movement occurred.
 */
enum StockMovementType: string implements HasColor, HasLabel
{
    case Sale = 'sale';
    case Restock = 'restock';
    case Adjustment = 'adjustment';
    case Return = 'return';
    case Damage = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::Restock => 'Restock',
            self::Adjustment => 'Adjustment',
            self::Return => 'Return',
            self::Damage => 'Damage',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sale => 'info',
            self::Restock => 'success',
            self::Adjustment => 'warning',
            self::Return => 'gray',
            self::Damage => 'danger',
        };
    }
}
