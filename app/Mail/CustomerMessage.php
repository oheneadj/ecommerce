<?php

/**
 * An ad-hoc, staff-composed email sent to a customer.
 */

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * `$body` is already HTML — composed via Filament's RichEditor in the admin
 * panel (staff-authored, not user-submitted), not plain text — so it's
 * passed straight through rather than escaped/nl2br'd.
 */
class CustomerMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body);
    }
}
