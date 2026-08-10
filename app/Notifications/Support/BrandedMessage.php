<?php

/**
 * Applies the store's business name to outgoing customer notifications.
 */

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Models\StoreSetting;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Two separate touches, since each notification channel needs its own:
 * the mail "From" display name is already handled globally (see
 * `AppServiceProvider`'s `MessageSending` listener), so this only sets the
 * closing signature; SMS has no such global hook, so `sms()` prefixes the
 * body directly. Falls back to `config('app.name')` if no business name
 * has been set yet, so a fresh deployment never sends an unbranded-looking
 * "Regards,\n" with nothing after it.
 */
class BrandedMessage
{
    /**
     * Sets the mail's closing signature to the store's business name.
     */
    public static function mail(MailMessage $message): MailMessage
    {
        $businessName = StoreSetting::current()->business_name ?: config('app.name', 'Laravel');

        return $message->salutation("Regards,\n{$businessName}");
    }

    /**
     * Prefixes an SMS body with the store's business name, e.g.
     * "Acme Store: Order ORD-2026-000123 received." — skipped entirely
     * if no business name is set, since an unbranded SMS is still a
     * perfectly readable message on its own.
     */
    public static function sms(string $body): string
    {
        $businessName = StoreSetting::current()->business_name;

        return $businessName ? "{$businessName}: {$body}" : $body;
    }
}
