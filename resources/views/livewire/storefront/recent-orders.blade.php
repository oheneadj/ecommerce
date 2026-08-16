<x-card>
    <div class="flex items-center justify-between">
        <h2 class="flex items-center gap-2 text-lg font-medium">
            <x-app-icon name="shopping-bag" class="size-5 text-zinc-400" />
            {{ __('Recent orders') }}
        </h2>
        <x-button href="{{ route('account.orders') }}" wire:navigate variant="ghost">{{ __('View all') }}</x-button>
    </div>

    @if ($this->orders->isEmpty())
        <p class="mt-4 text-sm text-zinc-500">{{ __("You haven't placed any orders yet.") }}</p>
    @else
        <div class="mt-4 divide-y divide-zinc-200">
            @foreach ($this->orders as $order)
                <a href="{{ route('account.orders.show', $order) }}" wire:navigate wire:key="order-{{ $order->id }}" class="-mx-2 flex items-center justify-between gap-4 rounded-lg px-2 py-3 transition-colors hover:bg-zinc-50">
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
