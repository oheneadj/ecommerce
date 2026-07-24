<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a coupon's discount is calculated.
 */
enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Fixed => 'Fixed Amount',
            self::FreeShipping => 'Free Shipping',
        };
    }
}
