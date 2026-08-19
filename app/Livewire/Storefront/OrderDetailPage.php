<?php

/**
 * Customer-facing order detail/tracking — a single order's items, shipping
 * destination, payment attempts, and status history timeline.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Actions\Order\GenerateOrderInvoice;
use App\Livewire\Storefront\Concerns\TracksPaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property-read string $addressLines
 * @property-read Payment|null $latestFailedPayment
 * @property-read bool $canDownloadInvoice
 * @property-read bool $hasPendingPayment
 */
#[Title('Order detail')]
class OrderDetailPage extends Component
{
    use TracksPaymentStatus;

    public Order $order;

    public function mount(string $orderUlid): void
    {
        $this->order = Auth::user()->orders()
            ->with(['items', 'payments', 'statusHistories', 'shipment'])
            ->where('ulid', $orderUlid)
            ->firstOrFail();
    }

    /**
     * Gates the "Download invoice" button — only shown once the invoice
     * actually exists. `invoice_path` isn't populated until
     * `SettlePaymentSuccess` dispatches `GenerateOrderInvoicePdf`, so this
     * alone already implies the order was paid — requiring
     * `status === Paid` *too* was a bug (bug hunt finding): it made the
     * button disappear the moment a paid order moved on to
     * Processing/Shipped/Delivered, even though the invoice file was
     * still sitting there and stayed downloadable via direct URL/admin.
     * Matches `OrderRecordActions::downloadInvoice()`'s own gate exactly.
     */
    #[Computed]
    public function canDownloadInvoice(): bool
    {
        return $this->order->invoice_path !== null;
    }

    /**
     * Mirrors the admin panel's own resilience fallback
     * (`OrderRecordActions::downloadInvoice()`): regenerates the PDF on
     * the fly if the file went missing from storage, rather than a raw
     * 404 — `GenerateOrderInvoice` renders exclusively from the order's
     * own permanently-snapshotted data, so this is always safe to re-run.
     */
    public function downloadInvoice(): ?StreamedResponse
    {
        if (! $this->canDownloadInvoice) {
            return null;
        }

        if (Storage::disk('local')->missing($this->order->invoice_path)) {
            GenerateOrderInvoice::run($this->order);
            $this->order->refresh();
        }

        return Storage::disk('local')->download($this->order->invoice_path, "{$this->order->order_number}.pdf");
    }

    /**
     * The order's snapshotted delivery address, flattened into a single
     * comma-separated display line — skips whichever parts weren't
     * captured (line2/region are often blank).
     */
    #[Computed]
    public function addressLines(): string
    {
        return collect([
            $this->order->address_snapshot['line1'] ?? null,
            $this->order->address_snapshot['line2'] ?? null,
            $this->order->address_snapshot['city'] ?? null,
            $this->order->address_snapshot['region'] ?? null,
        ])->filter()->implode(', ');
    }

    public function render(): View
    {
        return view('livewire.storefront.order-detail-page');
    }
}
