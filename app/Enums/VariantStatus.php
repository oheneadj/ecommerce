<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A product variant's sellability state.
 */
enum VariantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
