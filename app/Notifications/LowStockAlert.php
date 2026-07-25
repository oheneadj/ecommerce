<?php

/**
 * Alerts Store Keeper staff that a variant's stock has fallen to or below its threshold.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ProductVariant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every Store Keeper (BRD/agile-docs E10.2 — low-stock alerts go
 * to Store Keeper, not Admin). Database channel surfaces it on the
 * Filament admin bell; mail gives an off-panel heads-up.
 */
class LowStockAlert extends Notification
{
    public function __construct(
        private readonly ProductVariant $variant,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low stock: {$this->variant->sku}")
            ->greeting('Low stock alert')
            ->line("Variant {$this->variant->sku} has {$this->variant->stock} unit(s) left, at or below its threshold of {$this->variant->effectiveLowStockThreshold()}.")
            ->line('Consider restocking soon.');
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
}
