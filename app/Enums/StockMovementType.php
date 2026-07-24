<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The reason a stock movement occurred.
 */
enum StockMovementType: string
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
}
