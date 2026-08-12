<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A payment provider the Super Admin can activate from Store Settings.
 * Adding a new provider still means a new driver class + a config/payments.php
 * entry (never an Action change) plus a new case here — the enum only gates
 * the admin-facing choice, not driver resolution itself.
 */
enum PaymentProvider: string implements HasLabel
{
    case Paystack = 'paystack';
    case Moolre = 'moolre';

    public function label(): string
    {
        return match ($this) {
            self::Paystack => 'Paystack',
            self::Moolre => 'Moolre',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Presence-only check (never a live API call) — mirrors the existing
     * health-check convention of asserting credentials are set, not that
     * they're valid.
     */
    public function hasCredentialsConfigured(): bool
    {
        $credentials = (array) config("payments.providers.{$this->value}", []);

        return collect($credentials)->filter(fn ($value) => filled($value))->isNotEmpty();
    }
}
