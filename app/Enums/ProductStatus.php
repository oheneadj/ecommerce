<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A product's lifecycle state. "Archived" stops selling without deleting;
 * deletion (soft-delete + slug mutation) is a separate, distinct action.
 */
enum ProductStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Draft => 'Draft',
            self::Archived => 'Archived',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Draft => 'gray',
            self::Archived => 'danger',
        };
    }
}
