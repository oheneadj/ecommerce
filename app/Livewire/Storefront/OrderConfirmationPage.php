<?php

/**
 * Shown right after a customer places an order — confirms it was received
 * and links onward to its own tracking page. Payment confirmation itself
 * arrives later, asynchronously, via the payment gateway's webhook.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Order confirmed')]
class OrderConfirmationPage extends Component
{
    public Order $order;

    public function mount(string $orderUlid): void
    {
        $this->order = Order::query()
            ->where('ulid', $orderUlid)
            ->when(
                Auth::check(),
                fn ($query) => $query->where('user_id', Auth::id()),
                fn ($query) => $query->whereNull('user_id'),
            )
            ->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.storefront.order-confirmation-page');
    }
}
