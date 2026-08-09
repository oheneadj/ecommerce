<?php

/**
 * Customer-facing order history — every order this account has ever placed.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Order> $orders
 */
#[Title('My Orders')]
class OrderHistoryPage extends Component
{
    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        return Auth::user()->orders()->latest()->get();
    }

    public function render(): View
    {
        return view('livewire.storefront.order-history-page');
    }
}
