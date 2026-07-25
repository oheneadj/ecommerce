<?php

/**
 * Renders and stores a PDF receipt for an order.
 */

declare(strict_types=1);

namespace App\Actions\Order;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Renders exclusively from the order's own permanently-snapshotted data
 * (`Order` totals + each `OrderItem.item_snapshot`) — never live Product/
 * ProductVariant data, so a regenerated invoice for an old order looks
 * identical no matter what's since happened to the catalog (BRD Principle
 * 8 / AGENTS.md §8). Safe to call again later (e.g. from the admin panel)
 * — always overwrites `orders.invoice_path` with a freshly rendered file
 * from the same immutable source data, never a cached stale copy.
 */
class GenerateOrderInvoice
{
    use AsAction;

    public function handle(Order $order): string
    {
        $order->loadMissing('items', 'address');

        $pdf = Pdf::loadView('pdf.order-invoice', ['order' => $order]);

        $path = "invoices/{$order->order_number}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $order->update(['invoice_path' => $path]);

        return $path;
    }
}
