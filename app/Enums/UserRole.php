<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Staff roles managed via Spatie Laravel Permission. Customers never hold any of these roles.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case StoreKeeper = 'store_keeper';

    /**
     * Human-readable label for admin UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::StoreKeeper => 'Store Keeper',
        };
    }
}
