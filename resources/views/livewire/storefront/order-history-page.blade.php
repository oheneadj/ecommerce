<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('My Orders') }}</h1>

    <x-card>
        @if ($this->orders->isEmpty())
            <p class="text-sm text-zinc-500">{{ __("You haven't placed any orders yet.") }}</p>
        @else
            <div class="divide-y divide-zinc-200">
                @foreach ($this->orders as $order)
                    <a href="{{ route('account.orders.show', $order) }}" wire:navigate wire:key="order-{{ $order->id }}" class="flex items-center justify-between gap-4 py-3 hover:bg-zinc-50">
                        <div>
                            <p class="font-medium">{{ $order->order_number }}</p>
                            <p class="text-sm text-zinc-500">{{ $order->created_at->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-medium">{{ $order->grand_total_formatted }}</span>
                            <x-status-badge :color="$order->status->getColor()">{{ $order->status->getLabel() }}</x-status-badge>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>
</div>
