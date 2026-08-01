<?php

/**
 * Renders and stores an order's PDF invoice off the request cycle.
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Order\GenerateOrderInvoice;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PDF rendering is heavy/long-running work with no place blocking the
 * webhook or console process that confirmed the payment, per this
 * project's "heavy/long-running processing must be queued" convention.
 * Routed to the `processing` queue, kept separate from time-sensitive
 * `notifications`/`external-api` work.
 *
 * `GenerateOrderInvoice` itself stays callable synchronously too (e.g. an
 * admin manually regenerating an invoice wants immediate feedback) — only
 * this automatic post-payment call site is deferred.
 */
class GenerateOrderInvoicePdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(private readonly int $orderId)
    {
        $this->onQueue('processing');
    }

    public function handle(): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order === null) {
            return;
        }

        GenerateOrderInvoice::run($order);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateOrderInvoicePdf failed permanently', [
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
