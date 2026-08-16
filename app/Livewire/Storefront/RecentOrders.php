<?php

/**
 * The "Recent orders" widget on the customer account dashboard (/account) —
 * split out of AccountController/account.show into its own lazy-loaded
 * component so that query doesn't hold up the rest of the (otherwise
 * static) dashboard's first paint.
 */

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * @property-read Collection<int, Order> $orders
 */
#[Lazy]
class RecentOrders extends Component
{
    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        return Auth::user()->orders()->latest()->limit(5)->get();
    }

    public function render(): View
    {
        return view('livewire.storefront.recent-orders');
    }

    /**
     * Shown while the real component is still loading (see #[Lazy] above).
     */
    public function placeholder(): View
    {
        return view('livewire.storefront.recent-orders-placeholder');
    }
}
