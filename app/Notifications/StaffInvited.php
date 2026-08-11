<?php

/**
 * Invites a new staff account (or re-invites one just re-enabled) to set their password.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\UserRole;
use App\Notifications\Support\BrandedMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deliberately not the generic "forgot password" copy — this is an
 * invitation, not a self-service recovery. Both channels are required
 * (confirmed with the user): mail carries the actual set-password link;
 * SMS is a heads-up only — "you've been invited, check your email" — never
 * the link/token itself. SMS is unencrypted and the phone number isn't
 * OTP-verified at invite time (unlike customer phone auth), so it's the
 * wrong channel for anything bearing a credential. "No SMS for staff
 * login" per the user — SMS here is a notification, never an auth path.
 */
class StaffInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly UserRole $role,
        private readonly string $setPasswordUrl,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'sms'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return BrandedMessage::mail(
            (new MailMessage)
                ->subject("You've been invited to join as {$this->role->label()}")
                ->greeting("You're invited!")
                ->line("You've been invited to join as {$this->role->label()}.")
                ->action('Set your password', $this->setPasswordUrl)
                ->line('This link expires in 60 minutes.'),
        );
    }

    public function toSms(mixed $notifiable): string
    {
        return BrandedMessage::sms(
            "You've been invited to join as {$this->role->label()}. Check your email to set up your account.",
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('StaffInvited failed permanently', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
