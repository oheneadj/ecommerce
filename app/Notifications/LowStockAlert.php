<?php

/**
 * Alerts Store Keeper staff that a variant's stock has fallen to or below its threshold.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ProductVariant;
use App\Notifications\Support\BrandedMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sent to every Store Keeper (BRD/agile-docs E10.2 — low-stock alerts go
 * to Store Keeper, not Admin). Database channel surfaces it on the
 * Filament admin bell; mail gives an off-panel heads-up; sms is a
 * time-sensitive operational alert — unlike `StaffInvited`'s SMS, this one
 * carries no credential/link, so there's no reason to withhold detail from
 * it. `SmsChannel` no-ops gracefully for a Store Keeper with no phone on
 * file. Queued — see App\Notifications\OrderNotification's docblock for
 * why.
 */
class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly ProductVariant $variant,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'sms', 'database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low stock: {$this->variant->sku}")
            ->greeting('Low stock alert')
            ->line("Variant {$this->variant->sku} has {$this->variant->stock} unit(s) left, at or below its threshold of {$this->variant->effectiveLowStockThreshold()}.")
            ->line('Consider restocking soon.');
    }

    public function toSms(mixed $notifiable): string
    {
        return BrandedMessage::sms(
            "Low stock: {$this->variant->sku} has {$this->variant->stock} unit(s) left (threshold {$this->variant->effectiveLowStockThreshold()}).",
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'product_variant_id' => $this->variant->id,
            'sku' => $this->variant->sku,
            'stock' => $this->variant->stock,
            'threshold' => $this->variant->effectiveLowStockThreshold(),
            'message' => "Low stock: {$this->variant->sku} has {$this->variant->stock} unit(s) left.",
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('LowStockAlert failed permanently', [
            'product_variant_id' => $this->variant->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
