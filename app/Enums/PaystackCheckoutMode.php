<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How Paystack collects card details at checkout — the only provider this
 * applies to today. Moolre's mobile money flow has no browser checkout UI
 * to vary (request-to-pay, approved on the customer's own phone), so this
 * enum is deliberately Paystack-specific rather than a generic per-provider
 * concept.
 */
enum PaystackCheckoutMode: string implements HasLabel
{
    /**
     * The customer's whole browser is redirected to Paystack's hosted
     * checkout page, then sent back to `callback_url` afterwards.
     */
    case Redirect = 'redirect';

    /**
     * The transaction is initialized server-side as normal, but completed
     * in a JS popup on this site via Paystack's Inline.js
     * `resumeTransaction(accessCode)` — the customer never leaves the page.
     */
    case Popup = 'popup';

    public function label(): string
    {
        return match ($this) {
            self::Redirect => 'Redirect to Paystack',
            self::Popup => 'Popup (stay on this site)',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
