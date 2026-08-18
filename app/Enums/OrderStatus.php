<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * An order's fulfillment lifecycle state.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Paid => 'info',
            self::Processing => 'warning',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * The only statuses this one may transition to directly — enforced by
     * UpdateOrderStatus so an order can never skip a required state (e.g.
     * Pending straight to Delivered, with no payment or stock decrement
     * ever recorded) or reverse out of a state it already passed through.
     * Delivered and Cancelled are terminal — nothing changes them further.
     *
     * @return array<int, self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    /**
     * Whether stock was already decremented for this order by the time it
     * reached this status (i.e. payment settled) — used to decide whether
     * cancelling from here needs to restock, per UpdateOrderStatus.
     */
    public function hasDecrementedStock(): bool
    {
        return in_array($this, [self::Paid, self::Processing, self::Shipped], true);
    }
}
