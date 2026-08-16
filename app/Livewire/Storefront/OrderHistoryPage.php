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
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Order> $orders
 */
#[Title('My Orders')]
#[Lazy]
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

    /**
     * Shown while the real component is still loading (see #[Lazy] above)
     * — a full order's entire history can be a non-trivial query on a
     * long-time customer's account, so this gives the page something to
     * paint immediately instead of a blank gap.
     */
    public function placeholder(): View
    {
        return view('livewire.storefront.order-history-page-placeholder');
    }
}
