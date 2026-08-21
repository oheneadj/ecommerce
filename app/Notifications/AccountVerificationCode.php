<?php

/**
 * Delivers a one-time verification code to a customer's email.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Support\BrandedMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The email counterpart to phone+SMS OTP (RequestOtp) — used wherever an
 * account has no phone to receive a code on but still needs to re-verify
 * itself (e.g. a Google-only account confirming account deletion in
 * Settings). Deliberately plain: the actual code is the only thing that
 * matters here, purpose-specific copy stays in the calling Action.
 */
class AccountVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly string $code,
        private readonly string $reason,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return BrandedMessage::mail(
            (new MailMessage)
                ->subject('Your verification code')
                ->greeting('Your verification code')
                ->line($this->reason)
                ->line("**{$this->code}**")
                ->line('This code expires in 10 minutes.')
                ->line("If you didn't request this, you can safely ignore this email."),
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('AccountVerificationCode failed permanently', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
