<?php

/**
 * Alerts Admin staff that a stock correction left reservations uncovered.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sent to every Admin/Super Admin (BRD FR-2.2a) after
 * AdjustStockWithReservationCheck flags one or more reservations `at_risk`
 * — a manual correction is never blocked by this, but a human must resolve
 * the affected orders (contact customer, cancel, or expedite restock).
 * Queued — see App\Notifications\OrderNotification's docblock for why.
 */
class ReservationsAtRiskAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * @param  array<int, int>  $reservationIds
     */
    public function __construct(
        private readonly ProductVariant $variant,
        private readonly array $reservationIds,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $count = count($this->reservationIds);

        return (new MailMessage)
            ->subject("Reservations at risk: {$this->variant->sku}")
            ->greeting('Stock correction left reservations uncovered')
            ->line("A stock adjustment on variant {$this->variant->sku} left {$count} active reservation(s) without enough stock to cover them.")
            ->line('These have been flagged at_risk and need manual review — contact the customer, cancel the order, or expedite a restock.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'product_variant_id' => $this->variant->id,
            'sku' => $this->variant->sku,
            'reservation_ids' => $this->reservationIds,
            'message' => "Stock adjustment on {$this->variant->sku} left ".count($this->reservationIds).' reservation(s) at risk.',
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ReservationsAtRiskAlert failed permanently', [
            'product_variant_id' => $this->variant->id,
            'reservation_ids' => $this->reservationIds,
            'exception' => $exception->getMessage(),
        ]);
    }
}
