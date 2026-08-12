<?php

/**
 * Customer-facing order detail/tracking — a single order's items, shipping
 * destination, payment attempts, and status history timeline.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read string $addressLines
 */
#[Title('Order detail')]
class OrderDetailPage extends Component
{
    public Order $order;

    public function mount(string $orderUlid): void
    {
        $this->order = Auth::user()->orders()
            ->with(['items', 'payments', 'statusHistories', 'shipment'])
            ->where('ulid', $orderUlid)
            ->firstOrFail();
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
