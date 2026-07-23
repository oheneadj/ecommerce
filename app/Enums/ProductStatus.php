<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A product's lifecycle state. "Archived" stops selling without deleting;
 * deletion (soft-delete + slug mutation) is a separate, distinct action.
 */
enum ProductStatus: string
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
}
